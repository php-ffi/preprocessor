<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListener\LoggerDiagnosticListener;
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\Exception\FileNotReadableException;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

#[CoversClass(LoggerDiagnosticListener::class)]
final class LoggerDiagnosticListenerTest extends TestCase
{
    /**
     * Creates a logger that keeps the records it is given.
     */
    private static function createLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<array-key, mixed>}>
             */
            public array $records = [];

            /**
             * Untyped parameters keep the class compatible with every
             * version of PSR-3.
             *
             * @param mixed $level
             * @param \Stringable|string $message
             * @param array<array-key, mixed> $context
             */
            public function log($level, $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };
    }

    public function testDiagnosticIsLoggedWithItsPlace(): void
    {
        $logger = self::createLogger();
        $file = self::createFile("int a;\n  #warning text\n", 'sys/a.h');

        (new LoggerDiagnosticListener($logger))->notify(new Diagnostic(Severity::Warning, '#warning text', $file, 9, 13));

        self::assertSame([[
            LogLevel::WARNING,
            '#warning text',
            ['file' => 'sys/a.h', 'line' => 2, 'column' => 3, 'offset' => 9, 'length' => 13],
        ]], $logger->records);
    }

    /**
     * @return iterable<non-empty-string, array{Severity, string}>
     */
    public static function levelDataProvider(): iterable
    {
        yield 'warning' => [Severity::Warning, LogLevel::WARNING];
        yield 'error' => [Severity::Error, LogLevel::ERROR];
    }

    #[DataProvider('levelDataProvider')]
    public function testSeverityIsLoggedAsItsLevel(Severity $severity, string $level): void
    {
        $logger = self::createLogger();

        (new LoggerDiagnosticListener($logger))->notify(new Diagnostic($severity, 'text', self::createFile('a'), 0));

        self::assertCount(1, $logger->records);
        self::assertSame($level, $logger->records[0][0]);
    }

    public function testPlaceInUnreadableFileIsLoggedWithoutLineAndColumn(): void
    {
        $logger = self::createLogger();

        $file = new class implements FileInterface {
            public readonly string $pathname;

            public function __construct()
            {
                $this->pathname = 'gone.h';
            }

            public function read(int $offset, int $bytes): string
            {
                throw FileNotReadableException::becauseFileNotReadable($this->pathname);
            }
        };

        (new LoggerDiagnosticListener($logger))->notify(new Diagnostic(Severity::Error, 'text', $file, 3, 2));

        self::assertSame(
            ['file' => 'gone.h', 'line' => null, 'column' => null, 'offset' => 3, 'length' => 2],
            $logger->records[0][2],
        );
    }

    public function testMessageIsLoggedWithoutPlaceholderReplacement(): void
    {
        $logger = self::createLogger();

        (new LoggerDiagnosticListener($logger))->notify(new Diagnostic(Severity::Warning, '{file}', self::createFile('a'), 0));

        self::assertSame('{file}', $logger->records[0][1]);
    }

    public function testDiagnosticsOfPreprocessingAreLogged(): void
    {
        $logger = self::createLogger();
        $preprocessor = new Preprocessor(listener: new LoggerDiagnosticListener($logger));

        (string) $preprocessor->preprocess(VirtualSource::createFromString('main.h', "\n#warning text\n"));

        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0][0]);
        self::assertSame('#warning text', $logger->records[0][1]);
        self::assertSame('main.h', $logger->records[0][2]['file']);
        self::assertSame(2, $logger->records[0][2]['line']);
    }
}
