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
    public static function emit($event, $arguments = array())
    {
        $apricot = self::getInstance();

        $apricot->wakeUpListeners($event, $arguments);
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
    public function wakeUpListeners($event, array $arguments)
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

            foreach($listeners as $listener) {

                self::emit('event', array($event, $listener['callback']));

                $listenerResponse = call_user_func_array($listener['callback'], $arguments);
                
                if (null !== $listenerResponse) {
                    return $listenerResponse;
                }
            }
        }

        return false;
    }
}