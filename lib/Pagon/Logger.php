<?php

namespace Pagon;

use Closure;

/**
 * @method debug(string $text)
 * @method info(string $text)
 * @method warning(string $text)
 * @method error(string $text)
 * @method critical(string $text)
 */
class Logger extends Fiber
{
    protected $options = array(
        'file'       => 'app.log',
        'auto_write' => true,
        'format'     => '[$time] $token - $level - $text'
    );

    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;
    const CRITICAL = 4;

    protected static $levels = array('debug', 'info', 'warning', 'error', 'critical');
    protected static $messages = array();

    /**
     * @param array $options
     * @return self
     */
    public function __construct(array $options = array())
    {
        $this->options = $options + $this->options;

        $this->time = function () {
            return date('Y-m-d H:i:s');
        };
        $this->token = $this->share(function () {
            return substr(sha1(uniqid()), 0, 6);
        });
        $this->level = $this->protect(function ($level) {
            return str_pad($level, 8, ' ', STR_PAD_BOTH);
        });
    }

    /**
     * Log
     *
     * @param string $text
     * @param int    $level
     */
    public function log($text, $level = self::INFO)
    {
        $message = array('text' => $text, 'level' => self::$levels[$level]);

        if (preg_match_all('/\$(\w+)/', $this->options['format'], $matches)) {
            $matches = $matches[1];
            foreach ($matches as $match) {
                if (!isset($this->$match)) continue;

                if ($this->$match instanceof Closure) {
                    $message[$match] = call_user_func($this->$match, $message[$match]);
                } else {
                    $message[$match] = $this->$match;
                }
            }
        }

        foreach ($message as $k => $v) {
            unset($message[$k]);
            $message['$' . $k] = $v;
        }

        self::$messages[] = strtr($this->options['format'], $message);

        if ($this->options['auto_write']) {
            $this->write();
        }
    }

    /**
     * Support level method call
     *
     * @example
     *
     *  $logger->debug('test');
     *  $logger->info('info');
     *  $logger->info('this is %s', 'a');
     *  $logger->info('this is :id', array(':id' => 1));
     *
     * @param $method
     * @param $arguments
     * @return mixed|void
     */
    public function __call($method, $arguments)
    {
        if (in_array($method, self::$levels)) {
            if (!isset($arguments[1])) {
                $text = $arguments[0];
            } else if (is_array($arguments[1])) {
                $text = strtr($arguments[0], $arguments[1]);
            } else if (is_string($arguments[1])) {
                $text = vsprintf($arguments[0], array_slice($arguments, 1));
            } else {
                $text = $arguments[0];
            }
            $this->log($text, array_search($method, self::$levels));
        }
    }

    /**
     * Write log to file
     */
    public function write()
    {
        foreach (self::$messages as $index => $message) {
            unset(self::$messages[$index]);
            file_put_contents($this->options['file'], $message . PHP_EOL, FILE_APPEND);
        }
    }
}
