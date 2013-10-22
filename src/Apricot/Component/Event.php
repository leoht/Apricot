<?php

namespace Apricot\Component;

trait Event
{
    /**
     * @var array
     */
    protected $listeners = array();
    
    /**
     * Registers an event listener.
     */
    public static function on($event, callable $callback, $priority = 0)
    {
        $apricot = self::getInstance();

        $apricot->listeners[$event][] = array(
            'callback' => $callback,
            'priority' => $priority,
        );
    }

    /**
     * Emits an event into Apricot and wake up its listeners.
     */
    public static function emit($event, $arguments = array(), callable $callback = null)
    {
        $apricot = self::getInstance();

        $response = $apricot->wakeUpListeners($event, $arguments, $callback);
    }

    /**
     * Clears all listeners for a given event.
     *
     * @param string $event
     */
    public static function clear($event)
    {
        $apricot = self::getInstance();

        unset($apricot->listeners[$event]);
    }


    /**
     * Wakes up listeners of a specific event.
     */
    public function wakeUpListeners($event, array $arguments, callable $callback = null)
    {
        foreach($this->listeners as $e => $listeners)
        {
            if ($e !== $event) {
                continue;
            }

            usort($listeners, function ($a, $b)
            {
                return $a['priority'] > $b['priority'] ? -1 : 1;
            });

            foreach($listeners as $listenerId => $listener) {

                self::emit('event', array($event, $listener['callback']));

                $listenerResponse = call_user_func_array($listener['callback'], $arguments);
                
                if (null !== $callback) {
                    call_user_func_array($callback, array($listenerResponse, $listenerId));
                }
            }
        }

        return false;
    }
}