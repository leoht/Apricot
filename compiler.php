<?php

require 'autoload.php';


function getCompiledDirectory($dirname, $currentDepth = 0)
{
    $dir = opendir($dirname);

    $compiled = "";

    echo "Compiling directory $dirname... \r\n";

    while ($file = readdir($dir)) {
        if ($file != '.' && $file != '..' && $file != 'Apricot.php') {

            if (false === strpos($file, '.')) { // dir
                $compiled .= getCompiledDirectory($dirname.'/'.$file, $currentDepth+1);
            } else {

                for ($i = 0 ; $i < $currentDepth ; $i++) {
                    echo "\t";
                }

                echo "-> Compiling $file... \r\n";

                $fileContent = file_get_contents($dirname.'/'.$file);
                // strip the PHP tag
                $fileContent = str_replace('<?php', '', $fileContent);

                $compiled .= $fileContent . PHP_EOL;
            }
        }
    }

    return $compiled;
}

$dirname = './src/Apricot/Component';

$date = date('D Y-m-d');

$compiled = <<<EOF
<?php
/**
 * Apricot framework (one-file version).
 * Compiled on $date.
 *
 * Copyright (c) 2013 Léonard Hetsch <leo.hetsch@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
EOF;

$compiled .= getCompiledDirectory($dirname);


$fileContent = file_get_contents($dirname.'/../Apricot.php');
// strip the PHP tag
$fileContent = str_replace('<?php', '', $fileContent);

$compiled .= $fileContent;

rename('Apricot.php', '~Apricot.php');

file_put_contents('Apricot.php', $compiled);
