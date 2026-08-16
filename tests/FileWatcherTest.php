<?php

declare(strict_types=1);

namespace PHPStreamServer\Test;

use PHPStreamServer\Plugin\FileMonitor\Internal\FileWatcherFactory;
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
    private const WATCHER_DELAY = 0.7;

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

    public static function watcherImplementations(): \Generator
    {
        yield [PollingFileWatcher::class];

        if (PHP_OS_FAMILY === 'Linux') {
            yield [InotifyFileWatcher::class];
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            yield [FSEventsFileWatcher::class];
        }
    }

    public static function watcherFiles(): \Generator
    {
        foreach (self::watcherImplementations() as [$watcherClass]) {
            yield [$watcherClass, '/file.txt', true];
            yield [$watcherClass, '/file.txt.bak', false];
            yield [$watcherClass, '/.env', true];
            yield [$watcherClass, '/.env.example', false];
            yield [$watcherClass, '/.env.local', false];
            yield [$watcherClass, '/nested1/.env', false];
            yield [$watcherClass, '/file.html', false];
            yield [$watcherClass, '/file.php', true];
            yield [$watcherClass, '/nested1/file.txt', false];
            yield [$watcherClass, '/nested1/file.php', true];
            yield [$watcherClass, '/nested1/.file.php', true];
            yield [$watcherClass, '/nested1/file.php.bak', false];
            yield [$watcherClass, '/nested1/nested2/file.txt', false];
            yield [$watcherClass, '/nested1/nested2/file.php', true];
            yield [$watcherClass, '/nested1/nested2/file.csv', true];
            yield [$watcherClass, '/nested3/nested4/file.csv', false];
            yield [$watcherClass, '/nested3/test1.html', true];
            yield [$watcherClass, '/nested3/test1.inc', true];
            yield [$watcherClass, '/nested3/test1.csv', false];
            yield [$watcherClass, '/nested3/test2.html', false];
        }
    }

    public function testFactorySelectsNativeWatcherForPlatform(): void
    {
        // Arrange
        $watcher = FileWatcherFactory::create([], static fn() => null);

        // Assert
        if (PHP_OS_FAMILY === 'Linux') {
            $this->assertInstanceOf(InotifyFileWatcher::class, $watcher);
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $this->assertInstanceOf(FSEventsFileWatcher::class, $watcher);
        } else {
            $this->assertInstanceOf(PollingFileWatcher::class, $watcher);
        }
    }

    public function testFallbackFactoryCreate(): void
    {
        // Assert
        $this->assertInstanceOf(PollingFileWatcher::class, FileWatcherFactory::create([], static fn() => null, PollingFileWatcher::class));
        $this->assertInstanceOf(InotifyFileWatcher::class, FileWatcherFactory::create([], static fn() => null, InotifyFileWatcher::class));
        $this->assertInstanceOf(FSEventsFileWatcher::class, FileWatcherFactory::create([], static fn() => null, FSEventsFileWatcher::class));
    }

    #[DataProvider('watcherFiles')]
    public function testReloadsForMatchingFileCreate(string $watcherClass, string $file, bool $isTriggerReload): void
    {
        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.txt'),
            new WatchRule($this->directory . '/.env'),
            new WatchRule($this->directory . '/**/*.php'),
            new WatchRule($this->directory . '/nested1/**/*.csv'),
            new WatchRule($this->directory . '/nested3/test1.{html,inc}'),
        ];
        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($file): void {
            \file_put_contents($this->directory . $file, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        if ($isTriggerReload) {
            $this->assertNotEmpty($reloads);
        } else {
            $this->assertEmpty($reloads);
        }
    }

    #[DataProvider('watcherFiles')]
    public function testReloadsForMatchingFileChange(string $watcherClass, string $file, bool $isTriggerReload): void
    {
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
        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($filePath): void {
            \file_put_contents($filePath, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        if ($isTriggerReload) {
            $this->assertNotEmpty($reloads);
        } else {
            $this->assertEmpty($reloads);
        }
    }

    #[DataProvider('watcherImplementations')]
    public function testFileInParentDirDoesNotTriggerReload(string $watcherClass): void
    {
        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/**/*.php'),
        ];
        $filePath = \dirname($this->directory) . '/test.php';
        \file_put_contents($filePath, 'initial') ?: $this->fail('Failed to write to file');
        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($filePath): void {
            \file_put_contents($filePath, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadWithoutOpcacheReset(string $watcherClass): void
    {
        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.php', true),
            new WatchRule($this->directory . '/*.txt'),
        ];
        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function (): void {
            \file_put_contents($this->directory . '/file.txt', \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertSame([false], $reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadWithOpcacheReset(string $watcherClass): void
    {
        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/*.php', true),
            new WatchRule($this->directory . '/*.txt'),
        ];
        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function (): void {
            \file_put_contents($this->directory . '/file.php', \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertSame([true], $reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testFileInCreatedDirTriggersReload($watcherClass): void
    {
        // Arrange
        $reloads = [];
        $watchRules = [
            new WatchRule($this->directory . '/**/*.php', true),
        ];
        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        $path = $this->directory . '/created/nested/file.php';
        \mkdir(\dirname($path), recursive: true);
        EventLoop::delay(0.1, static function () use ($path): void {
            \file_put_contents($path, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testFileInMovedDirTriggersReload(string $watcherClass): void
    {
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

        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, static function () use ($incomingDirectory, $watchedDirectory): void {
            \rename($incomingDirectory, $watchedDirectory . '/moved1');
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testMovedOutDirIsUnwatched(string $watcherClass): void
    {
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

        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, static function () use ($movedDirectory, $movedOutDirectory): void {
            \rename($movedDirectory, $movedOutDirectory);
        });
        EventLoop::delay(1, static function () use (&$reloads): void {
            $reloads = [];
        });
        EventLoop::delay(self::WATCHER_DELAY + 0.1, function () use ($movedFile): void {
            \file_put_contents($movedFile, \uniqid()) ?: $this->fail('Failed to write to file');
        });
        EventLoop::delay(self::WATCHER_DELAY + self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadsAfterSourceDirectoryReplacement(string $watcherClass): void
    {
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

        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        EventLoop::delay(0.1, function () use ($sourceDirectory, $replacementDirectory): void {
            \rename($sourceDirectory, $this->directory . '/old-source');
            \rename($replacementDirectory, $sourceDirectory);
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }

    #[DataProvider('watcherImplementations')]
    public function testReloadsAfterSourceSymlinkReplacement(string $watcherClass): void
    {
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

        $watcher = FileWatcherFactory::create($watchRules, static function (bool $invalidateOpcache) use (&$reloads): void {
            $reloads[] = $invalidateOpcache;
        }, $watcherClass);
        $watcher->start();

        // Act
        $replacementLink = $this->directory . '/replacement-link';
        EventLoop::delay(0.1, static function () use ($secondTarget, $replacementLink, $sourceDirectory): void {
            \symlink($secondTarget, $replacementLink);
            \rename($replacementLink, $sourceDirectory);
        });
        EventLoop::delay(self::WATCHER_DELAY, $watcher->stop(...));
        EventLoop::run();

        // Assert
        $this->assertNotEmpty($reloads);
    }
}
