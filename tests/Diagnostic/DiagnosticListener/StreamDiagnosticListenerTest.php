<?php

declare(strict_types=1);

namespace FFI\Preprocessor\Tests\Diagnostic\DiagnosticListener;

use FFI\Preprocessor\Diagnostic\Diagnostic;
use FFI\Preprocessor\Diagnostic\Diagnostic\Severity;
use FFI\Preprocessor\Diagnostic\DiagnosticListener\StreamDiagnosticListener;
use FFI\Preprocessor\Exception\InvalidStreamException;
use FFI\Preprocessor\Preprocessor;
use FFI\Preprocessor\Tests\TestCase;
use Phplrt\Contracts\Source\FileInterface;
use Phplrt\Source\Exception\FileNotReadableException;
use Phplrt\Source\VirtualSource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StreamDiagnosticListener::class)]
#[CoversClass(Diagnostic::class)]
#[CoversClass(InvalidStreamException::class)]
final class StreamDiagnosticListenerTest extends TestCase
{
    /**
     * @return resource
     */
    private static function createStream(string $mode = 'w+b'): mixed
    {
        $stream = \fopen('php://memory', $mode);
        self::assertIsResource($stream);

        return $stream;
    }

    /**
     * @param resource $stream
     */
    private static function readStream(mixed $stream): string
    {
        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }

    public function testDiagnosticIsWrittenWithItsLineAndColumn(): void
    {
        $stream = self::createStream();
        $file = self::createFile("int a;\n  #warning text\n", 'sys/a.h');

        (new StreamDiagnosticListener($stream))->notify(new Diagnostic(Severity::Warning, '#warning text', $file, 9));

        self::assertSame("sys/a.h:2:3: warning: #warning text\n", self::readStream($stream));
    }

    public function testErrorIsWrittenWithItsSeverity(): void
    {
        $stream = self::createStream();
        $file = self::createFile("#include <a.h>\n", 'main.h');

        (new StreamDiagnosticListener($stream))->notify(new Diagnostic(Severity::Error, 'File <a.h> not found', $file, 9));

        self::assertSame("main.h:1:10: error: File <a.h> not found\n", self::readStream($stream));
    }

    public function testDiagnosticsAreWrittenOneLineEach(): void
    {
        $stream = self::createStream();
        $file = self::createFile("a\nb\n");
        $listener = new StreamDiagnosticListener($stream);

        $listener->notify(new Diagnostic(Severity::Warning, 'first', $file, 0));
        $listener->notify(new Diagnostic(Severity::Warning, 'second', $file, 2));

        self::assertSame("test.h:1:1: warning: first\ntest.h:2:1: warning: second\n", self::readStream($stream));
    }

    public function testDiagnosticOfUnreadableFileIsWrittenWithoutPosition(): void
    {
        $stream = self::createStream();

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

        (new StreamDiagnosticListener($stream))->notify(new Diagnostic(Severity::Warning, 'text', $file, 3));

        self::assertSame("gone.h: warning: text\n", self::readStream($stream));
    }

    public function testDiagnosticsOfPreprocessingAreWritten(): void
    {
        $stream = self::createStream();
        $preprocessor = new Preprocessor(listener: new StreamDiagnosticListener($stream));

        (string) $preprocessor->preprocess(VirtualSource::createFromString('main.h', "\n#warning text\n"));

        self::assertSame("main.h:2:1: warning: #warning text\n", self::readStream($stream));
    }

    public function testValueThatIsNotStreamIsRejected(): void
    {
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('The value of type "string" is not a stream');

        new StreamDiagnosticListener('php://stderr');
    }

    public function testClosedStreamIsRejected(): void
    {
        $stream = self::createStream();
        \fclose($stream);

        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('The value of type "resource (closed)" is not a stream');

        new StreamDiagnosticListener($stream);
    }

    public function testStreamOpenForReadingOnlyIsRejected(): void
    {
        $directory = $this->createDirectory(['a.h' => '']);
        $stream = \fopen($directory . '/a.h', 'rb');
        self::assertIsResource($stream);

        try {
            $this->expectException(InvalidStreamException::class);
            $this->expectExceptionMessageMatches('/\AThe stream ".+a\.h" is not open for writing\z/');

            new StreamDiagnosticListener($stream);
        } finally {
            \fclose($stream);
        }
    }

    public function testDefaultListenerAndStandardStreamsAreCreated(): void
    {
        self::assertInstanceOf(StreamDiagnosticListener::class, StreamDiagnosticListener::createDefault());
        self::assertInstanceOf(StreamDiagnosticListener::class, StreamDiagnosticListener::createFromStderr());
        self::assertInstanceOf(StreamDiagnosticListener::class, StreamDiagnosticListener::createFromStdout());
    }
}
