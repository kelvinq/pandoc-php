<?php
/**
 * Pandoc PHP
 *
 * Copyright (c) Ryan Kadwell <ryan@riaka.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pandoc;

/**
 * Naive wrapper for haskell's pandoc utility
 *
 * @author Ryan Kadwell <ryan@riaka.ca>
 */
class Pandoc
{
    /**
     * Where is the executable located
     * @var string
     */
    private $executable;

    /**
     * Where to take the content for pandoc from
     * @var string
     */
    private $tmpFile;

    /**
     * Directory to store temporary files
     * @var string
     */
    private $tmpDir;

    /**
     * List of valid input types
     * @var array
     * Updated for pandoc 2.13 (2021-03-21)
     */
    private $inputFormats = array(
        "bibtex",
        "biblatex",
        "commonmark",
        "commonmark_x",
        "creole",
        "csljson",
        "csv",
        "docbook",
        "docx",
        "dokuwiki",
        "epub",
        "fb2",
        "gfm",
        "markdown_github",
        "haddock",
        "html",
        "ipynb",
        "jats",
        "jira",
        "json",
        "latex",
        "markdown",
        "markdown_mmd",
        "markdown_phpextra",
        "markdown_strict",
        "mediawiki",
        "man",
        "muse",
        "native",
        "odt",
        "opml",
        "org",
        "rst",
        "t2t",
        "textile",
        "tikiwiki",
        "twiki",
        "vimwiki"
    );

    /**
     * List of valid output types
     * @var array
     * Updated for pandoc 2.13 (2021-03-21)
     */
    private $outputFormats = array(
        "asciidoc",
        "asciidoctor",
        "beamer",
        "bibtex",
        "biblatex",
        "commonmark",
        "commonmark_x",
        "context",
        "csljson",
        "docbook",
        "docbook4",
        "docbook5",
        "docx",
        "dokuwiki",
        "epub",
        "epub3",
        "epub2",
        "fb2",
        "gfm",
        "markdown_github",
        "haddock",
        "html",
        "html5",
        "html4",
        "icml",
        "ipynb",
        "jats_archiving",
        "jats_articleauthoring",
        "jats_publishing",
        "jats",
        "jira",
        "json",
        "latex",
        "man",
        "markdown",
        "markdown_mmd",
        "markdown_phpextra",
        "markdown_strict",
        "mediawiki",
        "ms",
        "muse",
        "native",
        "odt",
        "opml",
        "opendocument",
        "org",
        "pdf",
        "plain",
        "pptx",
        "rst",
        "rtf",
        "texinfo",
        "textile",
        "slideous",
        "slidy",
        "dzslides",
        "revealjs",
        "s5",
        "tei",
        "xwiki",
        "zimwiki"
    );

    /**
     * Setup path to the pandoc binary
     *
     * @param string $executable Path to the pandoc executable
     * @param string $tmpDir     Path to where we want to store temporary files
     */
    public function __construct($executable = null, $tmpDir = null)
    {
        if ( ! $tmpDir) {
            $tmpDir = sys_get_temp_dir();
        }

        if ( ! file_exists($tmpDir)) {
            throw new PandocException(
                sprintf('The directory %s does not exist!', $tmpDir)
            );
        }

        if ( ! is_writable($tmpDir)) {
            throw new PandocException(
                sprintf('Unable to write to the directory %s!', $tmpDir)
            );
        }

        $this->tmpDir = $tmpDir;

        $this->tmpFile = sprintf("%s/%s", $this->tmpDir, uniqid("pandoc"));

        // Since we can not validate that the command that they give us is
        // *really* pandoc we will just check that its something.
        // If the provide no path to pandoc we will try to find it on our own
        if ( ! $executable) {
            exec('which pandoc', $output, $returnVar);
            if ($returnVar === 0) {
                $this->executable = $output[0];
            } else {
                throw new PandocException('Unable to locate pandoc');
            }
        } else {
            $this->executable = $executable;
        }

        if ( ! is_executable($this->executable)) {
            throw new PandocException('Pandoc executable is not executable');
        }
    }

    /**
     * Run the conversion from one type to another
     *
     * @param string $from The type we are converting from
     * @param string $to   The type we want to convert the document to
     *
     * @return string
     */
    public function convert($content, $from, $to)
    {
        if ( ! in_array($from, $this->inputFormats)) {
            throw new PandocException(
                sprintf('%s is not a valid input format for pandoc', $from)
            );
        }

        if ( ! in_array($to, $this->outputFormats)) {
            throw new PandocException(
                sprintf('%s is not a valid output format for pandoc', $to)
            );
        }

        file_put_contents($this->tmpFile, $content);

        $command = sprintf(
            '%s --log=$s/pandoc.log --from=%s --to=%s %s',
            $this->tmpDir,
            $this->executable,
            $from,
            $to,
            $this->tmpFile
        );

        exec(escapeshellcmd($command), $output);

        return implode("\n", $output);
    }

    /**
     * Run the pandoc command with specific options.
     *
     * Provides more control over what happens. You simply pass an array of
     * key value pairs of the command options omitting the -- from the start.
     * If you want to pass a command that takes no argument you set its value
     * to null.
     *
     * @param string $content The content to run the command on
     * @param array  $options The options to use
     *
     * @return string The returned content
     */
    public function runWith($content, $options, $timeout = 0)
    {
        $commandOptions = array();

        $extFilesFormat = array(
            'docx',
            'odt',
            'epub',
            'fb2',
            'pdf'
        );

        $extFilesHtmlSlide = array(
            's5',
            'slidy',
            'dzslides',
            'slideous'
        );

        foreach ($options as $key => $value) {
            if ($key == 'to') {
                if (in_array($value, $extFilesFormat)) {
                    $commandOptions[] = '-s -S -o '.$this->tmpFile.'.'.$value;
                    $format = $value;
                    continue;
                } else if (in_array($value, $extFilesHtmlSlide)) {
                    $commandOptions[] = '-s -t '.$value.' -o '.$this->tmpFile.'.html';
                    $format = 'html';
                    continue;
                } else if ($value == 'epub3') {
                    $commandOptions[] = '-S -o '.$this->tmpFile.'.epub';
                    $format = 'epub';
                    continue;
                } else if ($value == 'beamer') {
                    $commandOptions[] = '-s -t beamer -o '.$this->tmpFile.'.pdf';
                    $format = 'pdf';
                    continue;
                } else if ($value == 'latex') {
                    $commandOptions[] = '-s -o '.$this->tmpFile.'.tex';
                    $format = 'tex';
                    continue;
                } else if ($value == 'rst') {
                    $commandOptions[] = '-s -t rst --toc -o '.$this->tmpFile.'.text';
                    $format = 'text';
                    continue;
                } else if ($value == 'rtf') {
                    $commandOptions[] = '-s -o '.$this->tmpFile.'.'.$value;
                    $format = $value;
                    continue;
                } else if ($value == 'docbook') {
                    $commandOptions[] = '-s -S -t docbook -o '.$this->tmpFile.'.db';
                    $format = 'db';
                    continue;
                } else if ($value == 'context') {
                    $commandOptions[] = '-s -t context -o '.$this->tmpFile.'.tex';
                    $format = 'tex';
                    continue;
                } else if ($value == 'asciidoc') {
                    $commandOptions[] = '-s -S -t asciidoc -o '.$this->tmpFile.'.txt';
                    $format = 'txt';
                    continue;
                }
            }

            if (null === $value) {
                $commandOptions[] = "--$key";
                continue;
            }

            if (is_array($value)) {
                foreach($value as $k => $v) {
                    $commandOptions[] = "--$key=$v";
                }
                continue;
            }

            $commandOptions[] = "--$key=$value";
        }

        file_put_contents($this->tmpFile, $content);
        chmod($this->tmpFile, 0777);
        $timeout = floatval($timeout);
        $ktimeout = $timeout * 2;
        $exe = $timeout > 0 ? "timeout -k {$ktimeout}s {$timeout}s {$this->executable}" : $this->executable;
        $command = sprintf(
            "%s %s %s",
            $exe,
            implode(' ', $commandOptions),
            $this->tmpFile
        );

        exec(escapeshellcmd($command), $output, $returnval);
        if($returnval === 0)
        {
            if (isset($format)) {
                return file_get_contents($this->tmpFile.'.'.$format);
            } else {
                return implode("\n", $output);
            }
        }else
        {
            throw new PandocException(
                sprintf('Pandoc could not convert successfully, error code: %s. Tried to run the following command: %s', $returnval, $command)
            );
        }
    }

    /**
     * Remove the temporary files that were created
     */
    public function __destruct()
    {
        if (file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }

        foreach (glob($this->tmpFile.'*') as $filename) {
            @unlink($filename);
        }
    }

    /**
     * Returns the pandoc version number
     *
     * @return string
     */
    public function getVersion()
    {
        exec(sprintf('%s --version', $this->executable), $output);

        return trim(str_replace('pandoc', '', $output[0]));
    }

    /**
     * Return that path where we are storing temporary files
     * @return string
     */
    public function getTmpDir()
    {
        return $this->tmpDir;
    }
}
