<?php

declare(strict_types = 1);

namespace Histel951\Dota2Api\Tests\Unit\Architecture;

use FilesystemIterator;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Проверяет правила зависимостей между слоями бандла по всем ссылкам на классы в src/.
 *
 * Слои источника S (OpenDota, Stratz) и контекста C (Match, Team) задаются статически: S\C\Domain, S\C\Port,
 * S\C\Application, S\Infrastructure и клиент источника S\<S>Client.
 */
final class LayerDependenciesTest extends TestCase
{
    private const ROOT = 'Histel951\\Dota2Api\\';

    private const SOURCES = ['OpenDota', 'Stratz'];

    private const CONTEXTS = ['Match', 'Team'];

    /** Общие слои вне источников. */
    private const COMMON_LAYERS = [
        'SharedKernel',
        'Exception',
        'Infrastructure',
        'Client\\Config',
        'Client',
        'DependencyInjection',
        'Resources',
        'Dota2ApiBundle',
    ];

    /** Composition root: без ограничений на исходящие зависимости. */
    private const UNRESTRICTED = ['Client', 'Client\\Config', 'DependencyInjection', 'Resources', 'Dota2ApiBundle'];

    /** SharedKernel может ссылаться только на этот класс вне себя. */
    private const SHARED_KERNEL_ONLY = 'Histel951\\Dota2Api\\Exception\\Dota2ApiExceptionInterface';

    public static function sourceProvider(): array
    {
        return ['OpenDota' => ['OpenDota'], 'Stratz' => ['Stratz']];
    }

    public function test_srcFollowsLayerDependencyRules(): void
    {
        $srcDir = str_replace('\\', '/', dirname(__DIR__, 3) . '/src');
        $violations = [];
        $files = 0;

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            ++$files;
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($srcDir) + 1);
            $fqcn = self::ROOT . str_replace('/', '\\', substr($relative, 0, -4));
            array_push($violations, ...$this->analyze((string) file_get_contents($file->getPathname()), $fqcn, 'src/' . $relative));
        }

        self::assertGreaterThan(50, $files, 'Не найдены файлы src/');
        self::assertSame([], $violations, "Нарушения правил слоёв:\n" . implode("\n", $violations));
    }

    #[DataProvider('sourceProvider')]
    public function test_detectsDomainDependingOnInfrastructure(string $source): void
    {
        $code = <<<PHP
            <?php
            namespace Histel951\\Dota2Api\\{$source}\\Match\\Domain\\Foo;
            use Histel951\\Dota2Api\\{$source}\\Infrastructure\\ApiGateway;
            final class Foo { public function __construct(private ApiGateway \$gateway) {} }
            PHP;

        $violations = $this->analyze($code, "Histel951\\Dota2Api\\{$source}\\Match\\Domain\\Foo\\Foo", 'Foo.php');

        self::assertContains("Foo.php:3 {$source}\\Match\\Domain → Histel951\\Dota2Api\\{$source}\\Infrastructure\\ApiGateway", $violations);
    }

    public function test_detectsFullyQualifiedReferenceWithoutUseStatement(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota\Match\Domain;
            final class Foo { public function run(): void { new \Histel951\Dota2Api\OpenDota\Infrastructure\ApiGateway(); } }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\Match\\Domain\\Foo', 'Foo.php');

        self::assertSame(['Foo.php:3 OpenDota\\Match\\Domain → Histel951\\Dota2Api\\OpenDota\\Infrastructure\\ApiGateway'], $violations);
    }

    #[DataProvider('sourceProvider')]
    public function test_detectsMatchDependingOnTeam(string $source): void
    {
        $code = <<<PHP
            <?php
            namespace Histel951\\Dota2Api\\{$source}\\Match\\Port;
            use Histel951\\Dota2Api\\{$source}\\Team\\Domain\\Team;
            interface Foo { public function team(): Team; }
            PHP;

        $violations = $this->analyze($code, "Histel951\\Dota2Api\\{$source}\\Match\\Port\\Foo", 'Foo.php');

        self::assertContains("Foo.php:3 {$source}\\Match\\Port → Histel951\\Dota2Api\\{$source}\\Team\\Domain\\Team", $violations);
    }

    #[DataProvider('sourceProvider')]
    public function test_detectsTeamDependingOnMatch(string $source): void
    {
        $code = <<<PHP
            <?php
            namespace Histel951\\Dota2Api\\{$source}\\Team\\Port;
            use Histel951\\Dota2Api\\{$source}\\Match\\Domain\\Match;
            interface Foo { public function match(): MatchRecord; }
            PHP;

        $violations = $this->analyze($code, "Histel951\\Dota2Api\\{$source}\\Team\\Port\\Foo", 'Foo.php');

        self::assertContains("Foo.php:3 {$source}\\Team\\Port → Histel951\\Dota2Api\\{$source}\\Match\\Domain\\Match", $violations);
    }

    public function test_detectsOpenDotaDependingOnStratz(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota\Match\Application;
            use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
            final class Foo { public function m(): MatchDetail {} }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\Match\\Application\\Foo', 'Foo.php');

        self::assertContains('Foo.php:3 OpenDota\\Match\\Application → Histel951\\Dota2Api\\Stratz\\Match\\Domain\\MatchDetail', $violations);
    }

    public function test_detectsStratzDependingOnOpenDota(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\Stratz\Match\Port;
            use Histel951\Dota2Api\OpenDota\Match\Domain\MatchDetail;
            interface Foo { public function m(): MatchDetail; }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\Stratz\\Match\\Port\\Foo', 'Foo.php');

        self::assertContains('Foo.php:3 Stratz\\Match\\Port → Histel951\\Dota2Api\\OpenDota\\Match\\Domain\\MatchDetail', $violations);
    }

    #[DataProvider('sourceProvider')]
    public function test_detectsSourceInfrastructureDependingOnOwnApplication(string $source): void
    {
        $code = <<<PHP
            <?php
            namespace Histel951\\Dota2Api\\{$source}\\Infrastructure\\Match;
            use Histel951\\Dota2Api\\{$source}\\Match\\Application\\MatchService;
            final class Foo { public function __construct(private MatchService \$service) {} }
            PHP;

        $violations = $this->analyze($code, "Histel951\\Dota2Api\\{$source}\\Infrastructure\\Match\\Foo", 'Foo.php');

        self::assertContains("Foo.php:3 {$source}\\Infrastructure → Histel951\\Dota2Api\\{$source}\\Match\\Application\\MatchService", $violations);
    }

    public function test_detectsSourceInfrastructureDependingOnClientOtherThanConfig(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota\Infrastructure;
            use Histel951\Dota2Api\Client\Dota2Client;
            use Histel951\Dota2Api\Client\Config\Dota2ClientConfig;
            final class Foo { public function __construct(Dota2ClientConfig $cfg) { new Dota2Client(); } }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\Infrastructure\\Foo', 'Foo.php');

        self::assertContains('Foo.php:3 OpenDota\\Infrastructure → Histel951\Dota2Api\Client\Dota2Client', $violations);
        self::assertCount(2, $violations);
    }

    public function test_detectsCommonInfrastructureDependingOnSource(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\Infrastructure\Mapping;
            use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGateway;
            final class Foo { public function __construct(private ApiGateway $gateway) {} }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\Infrastructure\\Mapping\\Foo', 'Foo.php');

        self::assertContains('Foo.php:3 Infrastructure → Histel951\\Dota2Api\\OpenDota\\Infrastructure\\ApiGateway', $violations);
    }

    public function test_detectsSourceClientDependingOnInfrastructure(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota;
            use Histel951\Dota2Api\OpenDota\Infrastructure\ApiGateway;
            final class OpenDotaClient { public function __construct(private ApiGateway $gateway) {} }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\OpenDotaClient', 'OpenDotaClient.php');

        self::assertContains('OpenDotaClient.php:3 OpenDota\\OpenDotaClient → Histel951\\Dota2Api\\OpenDota\\Infrastructure\\ApiGateway', $violations);
    }

    public function test_detectsSharedKernelDependingOnMatch(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\SharedKernel;
            use Histel951\Dota2Api\OpenDota\Match\Domain\Match;
            final class Foo { public function m(): MatchRecord {} }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\SharedKernel\\Foo', 'Foo.php');

        self::assertContains('Foo.php:3 SharedKernel → Histel951\\Dota2Api\\OpenDota\\Match\\Domain\\Match', $violations);
    }

    public function test_sharedKernelMayReferenceOnlyDota2ApiExceptionInterface(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\SharedKernel\Exception;
            use Histel951\Dota2Api\Exception\Dota2ApiExceptionInterface;
            use Histel951\Dota2Api\Exception\ApiGatewayException;
            interface Foo extends Dota2ApiExceptionInterface {}
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\SharedKernel\\Exception\\Foo', 'Foo.php');

        self::assertSame(['Foo.php:4 SharedKernel → Histel951\\Dota2Api\\Exception\\ApiGatewayException'], $violations);
    }

    public function test_detectsFileInUnknownLayer(): void
    {
        $violations = $this->analyze('<?php class A {}', 'Histel951\\Dota2Api\\Unknown\\A', 'Unknown/A.php');

        self::assertSame(['Unknown/A.php:0 слой файла не определён (Histel951\\Dota2Api\\Unknown\\A)'], $violations);
    }

    public function test_detectsTargetInUnknownLayer(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota\Match\Domain;
            use Histel951\Dota2Api\Brand\NewThing\Thing;
            final class Foo { public function t(): Thing {} }
            PHP;

        $violations = $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\Match\\Domain\\Foo', 'Foo.php');

        self::assertContains('Foo.php:3 OpenDota\\Match\\Domain → Histel951\\Dota2Api\\Brand\\NewThing\\Thing (слой цели не определён)', $violations);
    }

    public function test_allowsPermittedDependenciesAndSelf(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota\Match\Application;
            use Histel951\Dota2Api\OpenDota\Match\Domain\Match;
            use Histel951\Dota2Api\OpenDota\Match\Port\MatchSource;
            use Histel951\Dota2Api\SharedKernel\Foo;
            use Histel951\Dota2Api\Exception\ApiGatewayException;
            use Histel951\Dota2Api\OpenDota\Match\Application\Other;
            final class Svc { public function __construct(MatchRecord $m, MatchSource $s, Foo $f, Other $o, \Throwable $t) {} }
            PHP;

        self::assertSame([], $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\Match\\Application\\Svc', 'Svc.php'));
    }

    public function test_allowsSourceInfrastructureOnCommonInfrastructureAndClientConfig(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\Stratz\Infrastructure;
            use Histel951\Dota2Api\Client\Config\ClientConfig;
            use Histel951\Dota2Api\Exception\SourceDataException;
            use Histel951\Dota2Api\Infrastructure\Http\HttpMethod;
            use Histel951\Dota2Api\Infrastructure\Mapping\AbstractMapper;
            use Histel951\Dota2Api\SharedKernel\MatchId;
            use Histel951\Dota2Api\Stratz\Match\Domain\MatchDetail;
            use Histel951\Dota2Api\Stratz\Match\Port\MatchSourceInterface;
            use Histel951\Dota2Api\Stratz\Team\Port\TeamSourceInterface;
            final class Foo { public function __construct(ClientConfig $c, AbstractMapper $m, HttpMethod $h, MatchId $id) {} }
            PHP;

        self::assertSame([], $this->analyze($code, 'Histel951\\Dota2Api\\Stratz\\Infrastructure\\Foo', 'Foo.php'));
    }

    public function test_allowsSourceClientOnOwnApplicationAndPort(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Histel951\Dota2Api\OpenDota;
            use Histel951\Dota2Api\OpenDota\Match\Application\MatchService;
            use Histel951\Dota2Api\OpenDota\Team\Port\TeamSourceInterface;
            final class OpenDotaClient { public function __construct(MatchService $m, TeamSourceInterface $t) {} }
            PHP;

        self::assertSame([], $this->analyze($code, 'Histel951\\Dota2Api\\OpenDota\\OpenDotaClient', 'OpenDotaClient.php'));
    }

    /**
     * @return list<string> нарушения в формате "file:line Layer → TargetFQCN"
     */
    private function analyze(string $code, string $fqcn, string $label): array
    {
        $layer = $this->layerOf($fqcn);
        if (null === $layer) {
            return [sprintf('%s:0 слой файла не определён (%s)', $label, $fqcn)];
        }
        if (in_array($layer, self::UNRESTRICTED, true)) {
            return [];
        }

        $violations = [];
        foreach ($this->collectReferences($code) as [$target, $line]) {
            $targetLayer = $this->layerOf($target);
            if (null === $targetLayer) {
                $violations[] = sprintf('%s:%d %s → %s (слой цели не определён)', $label, $line, $layer, $target);
                continue;
            }
            if ($targetLayer === $layer || $this->isAllowed($layer, $targetLayer, $target)) {
                continue;
            }
            $violations[] = sprintf('%s:%d %s → %s', $label, $line, $layer, $target);
        }

        return array_values(array_unique($violations));
    }

    private function isAllowed(string $layer, string $targetLayer, string $target): bool
    {
        if ('SharedKernel' === $layer) {
            return self::SHARED_KERNEL_ONLY === $target;
        }

        return in_array($targetLayer, self::allowedTargets()[$layer] ?? [], true);
    }

    /**
     * Слой => разрешённые целевые слои (белый список). SharedKernel обрабатывается отдельно.
     *
     * @return array<string, list<string>>
     */
    private static function allowedTargets(): array
    {
        $allowed = [
            'Exception' => [],
            'Infrastructure' => ['SharedKernel', 'Exception'],
        ];

        foreach (self::SOURCES as $s) {
            $infrastructure = ['SharedKernel', 'Exception', 'Infrastructure', 'Client\\Config'];
            $client = [];
            foreach (self::CONTEXTS as $c) {
                $allowed["$s\\$c\\Domain"] = ['SharedKernel'];
                $allowed["$s\\$c\\Port"] = ["$s\\$c\\Domain", 'SharedKernel', 'Exception'];
                $allowed["$s\\$c\\Application"] = ["$s\\$c\\Domain", "$s\\$c\\Port", 'SharedKernel', 'Exception'];
                array_push($infrastructure, "$s\\$c\\Domain", "$s\\$c\\Port");
                array_push($client, "$s\\$c\\Application", "$s\\$c\\Port");
            }
            $allowed["$s\\Infrastructure"] = $infrastructure;
            $allowed["$s\\{$s}Client"] = $client;
        }

        return $allowed;
    }

    /**
     * @return list<string>
     */
    private static function layers(): array
    {
        $layers = self::COMMON_LAYERS;
        foreach (self::SOURCES as $s) {
            $layers[] = "$s\\Infrastructure";
            $layers[] = "$s\\{$s}Client";
            foreach (self::CONTEXTS as $c) {
                foreach (['Domain', 'Port', 'Application'] as $part) {
                    $layers[] = "$s\\$c\\$part";
                }
            }
        }

        return $layers;
    }

    private function layerOf(string $fqcn): ?string
    {
        if (!str_starts_with($fqcn, self::ROOT)) {
            return null;
        }
        $rest = substr($fqcn, strlen(self::ROOT));
        $best = null;
        foreach (self::layers() as $prefix) {
            if (($rest === $prefix || str_starts_with($rest, $prefix . '\\')) && (null === $best || strlen($prefix) > strlen($best))) {
                $best = $prefix;
            }
        }

        return $best;
    }

    /**
     * @return list<array{string, int}> [FQCN цели, строка] для всех ссылок на классы бандла
     */
    private function collectReferences(string $code): array
    {
        $ast = (new ParserFactory())->createForHostVersion()->parse($code) ?? [];
        $collector = new class(self::ROOT) extends NodeVisitorAbstract {
            /** @var list<array{string, int}> */
            public array $refs = [];

            public function __construct(private readonly string $root)
            {
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Node\UseItem) {
                    $this->add($node->name->toString(), $node->getStartLine());
                } elseif ($node instanceof Node\Name && !$node->isSpecialClassName()) {
                    $resolved = $node->getAttribute('resolvedName');
                    if ($resolved instanceof Node\Name\FullyQualified) {
                        $this->add($resolved->toString(), $node->getStartLine());
                    } elseif ($node instanceof Node\Name\FullyQualified) {
                        $this->add($node->toString(), $node->getStartLine());
                    }
                }

                return null;
            }

            private function add(string $name, int $line): void
            {
                if (str_starts_with($name, $this->root)) {
                    $this->refs[] = [$name, $line];
                }
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        return $collector->refs;
    }
}
