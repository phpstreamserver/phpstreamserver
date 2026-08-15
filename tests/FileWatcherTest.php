<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Plugin\FileMonitor\Internal\FSEventsFileWatcher;
use PHPStreamServer\Plugin\FileMonitor\Internal\InotifyFileWatcher;
use PHPStreamServer\Plugin\FileMonitor\Internal\PollingFileWatcher;
use PHPStreamServer\Plugin\FileMonitor\WatchRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

final class FileWatcherTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        PollingFileWatcher::$pollingInterval = 0.3;
        $this->directory = \sys_get_temp_dir() . '/phpss-file-watcher-' . \uniqid() . '/src';
        \mkdir($this->directory . '/nested1/nested2', recursive: true);
        \mkdir($this->directory . '/nested3/nested4', recursive: true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            /** @var \SplFileInfo $file */
            $file->isLink() || !$file->isDir() ? \unlink($file->getPathname()) : \rmdir($file->getPathname());
        }

        \rmdir($this->directory);
        \rmdir(\dirname($this->directory));
    }

    /**
     * @return \Generator [OS_FAMILY, Watcher class, delay]
     */
    public static function watcherImplementations(): \Generator
    {
        yield ['', PollingFileWatcher::class, 0.7];
        yield ['Linux', InotifyFileWatcher::class, 0.4];
        yield ['Darwin', FSEventsFileWatcher::class, 0.4];
    }

    public static function watcherFiles(): \Generator
    {
        foreach (self::watcherImplementations() as [$systemFamily, $watcherClass, $delay]) {
            yield [$systemFamily, $watcherClass, $delay, '/file.txt', true];
            yield [$systemFamily, $watcherClass, $delay, '/file.txt.bak', false];
            yield [$systemFamily, $watcherClass, $delay, '/.env', true];
            yield [$systemFamily, $watcherClass, $delay, '/.env.local', false];
            yield [$systemFamily, $watcherClass, $delay, '/nested1/.env', false];
            yield [$systemFamily, $watcherClass, $delay, '/file.html', false];
            yield [$systemFamily, $watcherClass, $delay, '/file.php', true];
            yield [$systemFamily, $watcherClass, $delay, '/nested1/file.txt', false];
            yield [$systemFamily, $watcherClass, $delay, '/nested1/file.php', true];
            yield [$systemFamily, $watcherClass, $delay, '/nested1/nested2/file.txt', false];
            yield [$systemFamily, $watcherClass, $delay, '/nested1/nested2/file.php', true];
            yield [$systemFamily, $watcherClass, $delay, '/nested1/nested2/file.csv', true];
            yield [$systemFamily, $watcherClass, $delay, '/nested3/nested4/file.csv', false];
            yield [$systemFamily, $watcherClass, $delay, '/nested3/test1.html', true];
            yield [$systemFamily, $watcherClass, $delay, '/nested3/test1.inc', true];
            yield [$systemFamily, $watcherClass, $delay, '/nested3/test1.csv', false];
            yield [$systemFamily, $watcherClass, $delay, '/nested3/test2.html', false];
        }
    }

    #[DataProvider('watcherFiles')]
    public function testReloadsForMatchingFileCreate(string $systemFamily, string $watcherClass, float $delay, string $file, bool $isTriggerReload): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.txt'),
            new WatchRule($this->directory . '/.env'),
            new WatchRule($this->directory . '/**/*.php'),
            new WatchRule($this->directory . '/nested1/**/*.csv'),
            new WatchRule($this->directory . '/nested3/test1.{html,inc}'),
        ];
        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($file): void {
            \file_put_contents($this->directory . $file, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        if ($isTriggerReload) {
            $this->assertNotEmpty($reloads);
        } else {
            $this->assertEmpty($reloads);
        }
    }

    #[DataProvider('watcherFiles')]
    public function testReloadsForMatchingFileChange(string $systemFamily, string $watcherClass, float $delay, string $file, bool $isTriggerReload): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.txt'),
            new WatchRule($this->directory . '/.env'),
            new WatchRule($this->directory . '/**/*.php'),
            new WatchRule($this->directory . '/nested1/**/*.csv'),
            new WatchRule($this->directory . '/nested3/test1.{html,inc}'),
        ];
        $filePath = $this->directory . $file;
        \file_put_contents($filePath, 'initial') ?: $this->fail('Failed to write to file');
        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($filePath): void {
            \file_put_contents($filePath, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        if ($isTriggerReload) {
            $this->assertNotEmpty($reloads);
        } else {
            $this->assertEmpty($reloads);
        }
    }

    #[DataProvider('watcherImplementations')]
    public function testFileInParentDirDoesNotTriggerReload(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/**/*.php'),
        ];
        $filePath = \dirname($this->directory) . '/test.php';
        \file_put_contents($filePath, 'initial') ?: $this->fail('Failed to write to file');
        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($filePath): void {
            \file_put_contents($filePath, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadWithoutOpcacheReset(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.php', true),
            new WatchRule($this->directory . '/*.txt'),
        ];
        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function (): void {
            \file_put_contents($this->directory . '/file.txt', \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertSame([false], $reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadWithOpcacheReset(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.php', true),
            new WatchRule($this->directory . '/*.txt'),
        ];
        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function (): void {
            \file_put_contents($this->directory . '/file.php', \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertSame([true], $reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testFileInCreatedDirTriggersReload(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/**/*.php', true),
        ];
        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        $path = $this->directory . '/created/nested/file.php';
        \mkdir(\dirname($path), recursive: true);
        EventLoop::delay(0.1, static function () use ($path): void {
            \file_put_contents($path, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testFileInMovedDirTriggersReload(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $watchedDirectory = $this->directory . '/watched';
        $incomingDirectory = $this->directory . '/incoming';

        $reloads = [];
        $watchRules = [
            new WatchRule($watchedDirectory . '/**/*.php'),
        ];

        \mkdir($watchedDirectory);
        \mkdir($incomingDirectory . '/nested', recursive: true);
        \file_put_contents($incomingDirectory . '/nested/file.php', \uniqid()) ?: $this->fail('Failed to write to file');

        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, static function () use ($incomingDirectory, $watchedDirectory): void {
            \rename($incomingDirectory, $watchedDirectory . '/moved1');
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testMovedOutDirIsUnwatched(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $watchedDirectory = $this->directory . '/watched';
        $movedDirectory = $watchedDirectory . '/moved';
        $movedOutDirectory = $this->directory . '/moved-out';
        $movedFile = $movedOutDirectory . '/file.php';

        $reloads = [];
        $watchRules = [
            new WatchRule($watchedDirectory . '/**/*.php'),
        ];

        \mkdir($movedDirectory, recursive: true);
        \file_put_contents($movedDirectory . '/file.php', \uniqid()) ?: $this->fail('Failed to write to file');

        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, static function () use ($movedDirectory, $movedOutDirectory): void {
            \rename($movedDirectory, $movedOutDirectory);
        });
        EventLoop::delay($delay, static function () use (&$reloads): void {
            $reloads = [];
        });
        EventLoop::delay($delay + 0.1, function () use ($movedFile): void {
            \file_put_contents($movedFile, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay($delay + $delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadsAfterSourceDirectoryReplacement(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $sourceDirectory = $this->directory . '/source';
        $replacementDirectory = $this->directory . '/replacement';

        $reloads = [];
        $watchRules = [
            new WatchRule($sourceDirectory . '/**/*.php'),
        ];

        \mkdir($sourceDirectory . '/nested', recursive: true);
        \mkdir($replacementDirectory . '/nested', recursive: true);
        \file_put_contents($replacementDirectory . '/nested/file.php', \uniqid()) ?: $this->fail('Failed to write to file');

        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($sourceDirectory, $replacementDirectory): void {
            \rename($sourceDirectory, $this->directory . '/old-source');
            \rename($replacementDirectory, $sourceDirectory);
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadsAfterSourceSymlinkReplacement(string $systemFamily, string $watcherClass, float $delay): void
    {
        if ($systemFamily !== '' && $systemFamily !== PHP_OS_FAMILY) {
            $this->markTestSkipped();
        }

        // Arrange
        $sourceDirectory = $this->directory . '/current';
        $firstTarget = $this->directory . '/first';
        $secondTarget = $this->directory . '/second';

        $reloads = [];
        $watchRules = [
            new WatchRule($sourceDirectory . '/*.php'),
        ];

        \mkdir($firstTarget);
        \mkdir($secondTarget);
        \file_put_contents($secondTarget . '/file.php', \uniqid()) ?: $this->fail('Failed to write to file');
        \symlink($firstTarget, $sourceDirectory);

        $watcher = new $watcherClass($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        });
        $watcher->start();

        // Act
        $replacementLink = $this->directory . '/replacement-link';
        EventLoop::delay(0.1, static function () use ($secondTarget, $replacementLink, $sourceDirectory): void {
            \symlink($secondTarget, $replacementLink);
            \rename($replacementLink, $sourceDirectory);
        });
        EventLoop::delay($delay, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }
}
