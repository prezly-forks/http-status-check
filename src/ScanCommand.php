<?php

namespace Spatie\HttpStatusCheck;

use Spatie\Crawler\Crawler;
use GuzzleHttp\RequestOptions;
use Spatie\Crawler\CrawlAllUrls;
use Spatie\Crawler\CrawlInternalUrls;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ScanCommand extends Command
{
    protected function configure()
    {
        $this->setName('scan')
            ->setDescription('Check the http status code of all links on a website.')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'The url to check'
            )
            ->addOption(
                'concurrency',
                'c',
                InputOption::VALUE_REQUIRED,
                'The amount of concurrent connections to use',
                10
            )
            ->addOption(
                'dump',
                'd',
                InputOption::VALUE_REQUIRED,
                'Dump output in this (csv) file'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Log all non-2xx and non-3xx responses in this file'
            )
            ->addOption(
                'dont-crawl-external-links',
                'x',
                InputOption::VALUE_NONE,
                'Dont crawl external links'
            )
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'The maximum number of seconds the request can take',
                10
            );
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $baseUrl = $input->getArgument('url');
        $crawlProfile = $input->getOption('dont-crawl-external-links') ? new CrawlInternalUrls($baseUrl) : new CrawlAllUrls();

        $output->writeln("Start scanning {$baseUrl}");
        $output->writeln('');

        $crawlLogger = new CrawlLogger($output);

        if ($input->getOption('dump')) {
            $dumpFile = $input->getOption('dump');

            if (file_exists($dumpFile)) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    "The dump csv file `{$dumpFile}` already exists. Overwrite it? (y/n)",
                    false
                );

                if (! $helper->ask($input, $output, $question)) {
                    $output->writeln('Aborting...');

                    return 0;
                }
            }

            $crawlLogger->setDumpfile($input->getOption('dump'));
        }

        if ($input->getOption('output')) {
            $outputFile = $input->getOption('output');

            if (file_exists($outputFile)) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    "The output file `{$outputFile}` already exists. Overwrite it? (y/n)",
                    false
                );

                if (! $helper->ask($input, $output, $question)) {
                    $output->writeln('Aborting...');

                    return 0;
                }
            }

            $crawlLogger->setOutputFile($input->getOption('output'));
        }

        Crawler::create([RequestOptions::TIMEOUT => $input->getOption('timeout')])
            ->setConcurrency($input->getOption('concurrency'))
            ->setCrawlObserver($crawlLogger)
            ->setCrawlProfile($crawlProfile)
            ->startCrawling($baseUrl);

        return 0;
    }
}
