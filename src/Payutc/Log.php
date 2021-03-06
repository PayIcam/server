<?php

namespace Payutc;

use Monolog\Logger;
use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\TestHandler;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;

use Payutc\Config;
use Payutc\Log\ContextProcessor;
use Payutc\Log\IntrospectionProcessor;
use Payutc\Log\JsonFormatter;

class Log
{
    const DEV = 0;
    const PRD = 1;
    const TEST = 2;

    const DEBUG = Logger::DEBUG;
    const INFO = Logger::INFO;
    const NOTICE = Logger::NOTICE;
    const WARNING = Logger::WARNING;
    const ERROR = Logger::ERROR;
    const CRITICAL = Logger::CRITICAL;
    const ALERT = Logger::ALERT;
    const EMERGENCY = Logger::EMERGENCY;


    protected static $service = null;
    protected static $method = null;
    protected static $streamHandler = null;
    protected static $chromePhpHandler = null;
    protected static $logger = null;

    public static function init($mode = null, $filename = null)
    {
        if ($mode === null) {
            $mode = Config::get('log_mode');
        }
        else if (is_string($mode)) {
            switch ($mode) {
                case 'PRD':
                    $mode = self::PRD;
                break;
                case 'TEST':
                    $mode = self::TEST;
                break;
                case 'DEV':
                default:
                    $mode = self::DEV;
                break;
            }
        }
        if ($filename == null) {
            $filename = Config::get('log_filename');
            if ($filename === null) {
                $filename = 'default.log';
            }
        }

        self::$logger = new Logger('root');

        switch ($mode) {
            case self::PRD:
                $introspectionProcessorLevel = Logger::INFO;
                self::$streamHandler = new StreamHandler($filename, Logger::INFO);
                self::$streamHandler->setFormatter(new JsonFormatter());
            break;
            case self::TEST:
                $introspectionProcessorLevel = Logger::DEBUG;
                self::$streamHandler = new TestHandler(Logger::DEBUG, false);
            break;
            default:
            case self::DEV:
                $introspectionProcessorLevel = Logger::DEBUG;

                self::$chromePhpHandler = new ChromePHPHandler($filename, Logger::DEBUG);
                self::$streamHandler = new RotatingFileHandler($filename, Logger::DEBUG);
                self::$streamHandler->setFormatter(new JsonFormatter(true));
            break;
        }

        // get informations about the file, the function, etc...
        self::$streamHandler->pushProcessor(new WebProcessor());
        self::$logger->pushProcessor(new IntrospectionProcessor($introspectionProcessorLevel));
        self::$logger->pushProcessor(new ContextProcessor());
        self::$logger->pushProcessor(new UidProcessor(32));

        // add the handlers
        if (self::$chromePhpHandler) {
            self::$logger->pushHandler(self::$chromePhpHandler);
        }
        self::$logger->pushHandler(self::$streamHandler);

        ErrorHandler::register(self::$logger);
    }

    public static function debug($msg, $data = array(), $e = null) {
        if ($e !== null) {
            $data['exception'] = static::exceptionToArray($e);
        }
        if(method_exists(self::$logger, 'addDebug')) {
            self::$logger->addDebug($msg, $data);
        } else {
            self::$logger->debug($msg, $data);
        }
    }

    public static function info($msg, $data = array(), $e = null) {
        if ($e !== null) {
            $data['exception'] = static::exceptionToArray($e);
        }
        if(method_exists(self::$logger, 'addInfo')) {
            self::$logger->addInfo($msg, $data);
        } else {
            self::$logger->info($msg, $data);
        }
    }

    public static function warning($msg, $data = array(), $e = null) {
        if ($e !== null) {
            $data['exception'] = static::exceptionToArray($e);
        }
        if(method_exists(self::$logger, 'addWarning')) {
            self::$logger->addWarning($msg, $data);
        } else {
            self::$logger->warning($msg, $data);
        }
    }

    public static function warn($msg, $data = array(), $e = null) {
        self::warning($msg, $data, $e);
    }

    public static function error($msg, $data = array(), $e = null) {
        if ($e !== null) {
            $data['exception'] = static::exceptionToArray($e);
        }
        if(method_exists(self::$logger, 'addError')) {
            self::$logger->addError($msg, $data);
        } else {
            self::$logger->error($msg, $data);
        }
    }

    public static function critical($msg, $data = array(), $e = null) {
        if ($e !== null) {
            $data['exception'] = static::exceptionToArray($e);
        }
        if(method_exists(self::$logger, 'addCritical')) {
            self::$logger->addCritical($msg, $data);
        } else {
            self::$logger->critical($msg, $data);
        }
    }

    public static function getLogger() {
        return self::$logger;
    }

    public static function getStreamHandler() {
        return self::$streamHandler;
    }

    public static function exceptionToArray($e) {
        return array(
            "message" => $e->getMessage(),
            "file" => $e->getFile(),
            "line" => $e->getLine(),
            "trace" => $e->getTrace()
        );
    }
}


