#!/usr/bin/env php
<?php
/**
 * uniconv
 *
 * Command Line universal converter for any PDO-available databases
 *
 * @copyright   2015 Robin de Graaf, devvoh webdevelopment
 * @license     MIT
 * @author      Robin de Graaf (hello@devvoh.com)
 */

class cli {

    static $parameters = array();
    static $lastProgressLength = 0;
    static $lines = array();

    public static function write($message) {
        echo $message . PHP_EOL;
    }

    public static function dump($message) {
        print_r($message);
        echo PHP_EOL;
    }
    
    public static function addLine($message) {
        self::$lines[] = $message;
    }
    
    public static function writeLines() {
        $output = implode(self::$lines, PHP_EOL);
        self::write($output);
    }

    public static function nl() {
        echo PHP_EOL;
    }

    public static function parseParameters($params) {
        // Check for parameters given
        for ($i = 1; $i < count($params); $i++) {
            if (substr($params[$i], 0, 1) === '-') {
                // set the current param as key and the next one as value
                $key = str_replace('-', '', $params[$i]);
                self::$parameters[$key] = $params[$i+1];
                // and skip the value
                $i++;
            } else {
                // Set the parameters as key and true as value
                self::$parameters[$params[$i]] = true;
            }
        }
    }
    
    public static function getParameters() {
        return self::$parameters;
    }

    public static function getParameter($key) {
        if (isset(self::$parameters[$key])) {
            return self::$parameters[$key];
        }
        return null;
    }

    public static function yesNo($question, $default = true) {
        // output question and appropriate default value
        echo trim($question) . ($default ? ' [Y/n] ' : ' [y/N] ');
        // get user input from stdin
        $line = fgets(STDIN);
        // turn into lowercase and check specifically for yes and no, call ourselves again if neither
        $value = strtolower(trim($line));

        if (in_array($value, array('y', 'yes'))) {
            return true;
        } elseif (in_array($value, array('n', 'no'))) {
            return false;
        } elseif (empty($value)) {
            // but if it's empty, assume default
            return $default;
        }
        // If nothing has been returned so far, keep asking
        echo "Enter y/yes or n/no.\n";
        return self::yesNo($question, $default);
    }

    public static function progress($message) {
        if (self::$lastProgressLength > 0) {
            echo "\e[" . self::$lastProgressLength . "D";
        }
        self::$lastProgressLength = strlen($message);
        echo $message;
    }
    
    public static function end() {
        self::writeLines();
        exit;
    } 
    
}

class config {
    
    public static $filename = null;
    public static $config = null;
    
    public static function setFilename($filename) {
        self::$filename = $filename;
    }
    
    public static function getFilename() {
        return self::$filename;
    }
    
    public static function load() {
        if (!self::$filename) {
            cli::addLine('No config filename given. Use uniconv -config config.json');
            return false;
        }
        if (!file_exists(self::$filename)) {
            cli::addLine('Config file does not exist: ' . self::$filename);
            return false;
        }
        self::$config = json_decode(file_get_contents(self::$filename), true);
        if (!self::$config) {
            cli::addLine('Invalid config file: ' . self::$filename);
            return false;
        }
        
        // Now set the relevant config values on the converter
        converter::setSettings(self::$config['config']['settings']);
        converter::setDbSource(self::$config['config']['databases']['source']);
        converter::setDbTarget(self::$config['config']['databases']['target']);
        converter::setConversions(self::$config['conversions']);
        return true;
    }
    
    public static function getConfig($key = null) {
        return self::$config;
    }
    
}

class converter {

    public static $settings = array();
    public static $dbSource = array();
    public static $dbTarget = array();
    public static $conversions = array();

    public static function setSettings($settings) {
        self::$settings['pass_size']        = (int)$settings['pass_size'];
        self::$settings['ignore_errors']    = (int)$settings['ignore_errors'];
        self::$settings['log_file']         = (int)$settings['log_file'];
    }
    
    public static function getSettings($key) {
        if ($key) {
            if (isset(self::$settings[$key])) {
                return self::$settings[$key];
            } else {
                return null;
            }
        }
        return self::$settings;
    }

    public static function setDbSource($dbSource) {
        self::$dbSource = $dbSource;
    }
    
    public static function getDbSource($key = null) {
        if ($key) {
            if (isset(self::$dbSource[$key])) {
                return self::$dbSource[$key];
            } else {
                return null;
            }
        }
        return self::$dbSource;
        
    }

    public static function setDbTarget($dbTarget) {
        self::$dbTarget = $dbTarget;
    }
    
    public static function getDbTarget($key = null) {
        if ($key) {
            if (isset(self::$dbTarget[$key])) {
                return self::$dbTarget[$key];
            } else {
                return null;
            }
        }
        return self::$dbTarget;
    }

    public static function setConversions($conversions) {
        self::$conversions = $conversions;
    }
    
    public static function getConversions($key = null) {
        if ($key) {
            if (isset(self::$conversions[$key])) {
                return self::$conversions[$key];
            } else {
                return null;
            }
        }
        return self::$conversions;
    }
    
}

cli::parseParameters($argv);

config::setFilename(cli::getParameter('config'));
if (!config::load()) {
    cli::end();
}

foreach (converter::getConversions() as $entity => $fields) {
    $total = 1000;//rand(0, 100);
    for ($i = 0; $i <= $total; $i++) {
        $padded = str_pad($i.'/'.$total, 12, ' ', STR_PAD_RIGHT);
        cli::progress($padded . 'Converting entity "' . $entity . '"...');
        // Sleep .01 seconds: 10000
        $sleep = 1000 * mt_rand(0, 1000);
        usleep($sleep);
    }
    cli::nl();
}