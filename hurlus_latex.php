<?php

declare(strict_types=1);

include_once(__DIR__ . '/vendor/autoload.php');

use Psr\Log\LogLevel;
use Oeuvres\Kit\{Filesys, Log};
use Oeuvres\Kit\Logger\{LoggerCli};
use Oeuvres\Teinte\Format\{Tei};

// direct call as CLI
if (
    php_sapi_name() == 'cli'
    && isset($argv[0])
    && realpath($argv[0]) == realpath(__FILE__)
) {
    Hurlus::cli();
}

class Hurlus
{
    /** Object to load Tei for exports */
    static $tei;
    /** Diectory where LaTeX hould work */
    static $work_dir;
    static $skeltex;

    public static function cli()
    {
        global $argv;
        Log::setLogger(new LoggerCli(LogLevel::DEBUG));
        if (!isset($argv[1])) {
            die("usage: php docx_tei.php examples/*.docx\n");
        }
        // drop $argv[0], $argv[1…] should be file
        array_shift($argv);
        self::$work_dir = __DIR__ . "/work/latex/";
        self::$tei = new Tei();
        // loop on arguments to get files of globs
        foreach ($argv as $glob) {
            foreach (glob($glob) as $tei_file) {
                self::pdf($tei_file);
            }
        }
    }
    public static function pdf($tei_file)
    {
        $oldPath = getcwd(); // keep old path
        // load TEI
        self::$tei->load($tei_file);
        $filename = pathinfo($tei_file, PATHINFO_FILENAME);
        // Tex will need a lot of files, generate latex in a tmp dir
        $dir = strtok($filename, '_');
        // the source latex file
        $latex_file = self::$work_dir . "$dir/{$filename}_105x297.tex";
        self::$tei->toUri(
            'latex', 
            $latex_file, 
            [
                'template' => __DIR__ . "/hurlus_booklet.tex",
                'latex.xsl' => __DIR__ . "/hurlus_latex.xsl",
            ]
        );
        // exec CLI xelatex
        chdir(dirname($latex_file)); // change working directory
        exec("latexmk -lualatex -quiet -f " . $latex_file);
        // to build the booklet, apply a tex script to generated pdf
        $tex = file_get_contents(__DIR__ .'/vendor/oeuvres/xsl/tei_latex/booklet_bind.tex');        
        // set the pdf file to transform (relative path)
        $tex = str_replace('{thepdf}', "{{$filename}_105x297}", $tex);
        // write the tex to process
        $dst_booklet = self::$work_dir . "$dir/{$filename}_booklet";

        file_put_contents("$dst_booklet.tex", $tex);
        // works with -pdf only
        exec("latexmk -pdf -quiet -f $dst_booklet.tex");
        chdir($oldPath); // restore old path
    }
}
