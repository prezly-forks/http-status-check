<?php

namespace Spatie\HttpStatusCheck;

use Spatie\Crawler\Url;
use Spatie\Crawler\CrawlObserver;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlLogger implements CrawlObserver
{
    const UNRESPONSIVE_HOST = 'Host did not respond';

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $consoleOutput;

    /**
     * @var array
     */
    protected $crawledUrls = [];

    /**
     * @var string|null
     */
    protected $outputFile = null;

    /**
     * @var string|null
     */
    protected $dumpFile = null;

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $consoleOutput
     */
    public function __construct(OutputInterface $consoleOutput)
    {
        $this->consoleOutput = $consoleOutput;
    }

    /**
     * Called when the crawl will crawl the url.
     *
     * @param \Spatie\Crawler\Url $url
     */
    public function willCrawl(Url $url)
    {
    }

    /**
     * Called when the crawler has crawled the given url.
     *
     * @param \Spatie\Crawler\Url $url
     * @param \Psr\Http\Message\ResponseInterface|null $response
     * @param \Spatie\Crawler\Url $foundOn
     */
    public function hasBeenCrawled(Url $url, $response, Url $foundOn = null)
    {
        $statusCode = $response ? $response->getStatusCode() : self::UNRESPONSIVE_HOST;

        $reason = $response ? $response->getReasonPhrase() : '';

        $colorTag = $this->getColorTagForStatusCode($statusCode);

        $timestamp = date('Y-m-d H:i:s');

        $message = "{$statusCode} {$reason} - ".(string) $url;

        if ($foundOn && $colorTag === 'error') {
            $message .= " (found on {$foundOn})";
        }

        if ($this->outputFile && $colorTag === 'error') {
            file_put_contents($this->outputFile, $message.PHP_EOL, FILE_APPEND);
        }

        $this->consoleOutput->writeln("<{$colorTag}>[{$timestamp}] {$message}</{$colorTag}>");

        $this->crawledUrls[$statusCode][] = $url;
    }

    /**
     * Called when the crawl has ended.
     */
    public function finishedCrawling()
    {
        $this->consoleOutput->writeln('');
        $this->consoleOutput->writeln('Crawling summary');
        $this->consoleOutput->writeln('----------------');

        ksort($this->crawledUrls);

        if ($this->dumpFile) {
            $message_comma_separated = "Url,Status Code,Host,Scheme,Port,Path,Query";
            file_put_contents($this->dumpFile, $message_comma_separated.PHP_EOL, FILE_APPEND);
        }

        foreach ($this->crawledUrls as $statusCode => $urls) {
            $colorTag = $this->getColorTagForStatusCode($statusCode);

            $count = count($urls);

            if (is_numeric($statusCode)) {
                $this->consoleOutput->writeln("<{$colorTag}>Crawled {$count} url(s) with status code {$statusCode}</{$colorTag}>");
            }

            if ($statusCode == static::UNRESPONSIVE_HOST) {
                $this->consoleOutput->writeln("<{$colorTag}>{$count} url(s) did have unresponsive host(s)</{$colorTag}>");
            }

            /* @var $url Url */
            foreach ($urls as $url) {
                if ($this->dumpFile) {
                    $message_comma_separated = implode(',',[
                        $url->__toString(),
                        $statusCode,
                        $url->host,
                        $url->scheme,
                        $url->port,
                        $url->path,
                        $url->query,
                    ]);

                    //$this->consoleOutput->writeln($message_comma_separated);
                    file_put_contents($this->dumpFile, $message_comma_separated.PHP_EOL, FILE_APPEND);
                }
            }

        }

        $this->consoleOutput->writeln('');

        if ($this->dumpFile) {
            $this->consoleOutput->writeln('');
            $this->consoleOutput->writeln('Also wrote output to ' . $this->dumpFile);
        }
    }

    protected function getColorTagForStatusCode(string $code): string
    {
        if ($this->startsWith($code, '2')) {
            return 'info';
        }

        if ($this->startsWith($code, '3')) {
            return 'comment';
        }

        return 'error';
    }

    /**
     * @param string|null $haystack
     * @param string|array $needles
     *
     * @return bool
     */
    public function startsWith($haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the filename to write the output log.
     *
     * @param string $filename
     */
    public function setOutputFile($filename)
    {
        $this->outputFile = $filename;
    }

    /**
     * Set the dump file (csv) to log all output to
     *
     * @param string $filename
     */
    public function setDumpFile($filename)
    {
        $this->dumpFile = $filename;
    }
}
