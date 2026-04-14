<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SmartReplyr_Loader {

    protected $actions = array();
    protected $filters = array();

    public function add_action( $hook, $component, $callback, $priority = 10, $args = 1 ) {
        $this->actions[] = array(
            'hook'      => $hook,
            'component' => $component,
            'callback'  => $callback,
            'priority'  => $priority,
            'args'      => $args,
        );
    }

    public function add_filter( $hook, $component, $callback, $priority = 10, $args = 1 ) {
        $this->filters[] = array(
            'hook'      => $hook,
            'component' => $component,
            'callback'  => $callback,
            'priority'  => $priority,
            'args'      => $args,
        );
    }

    public function run() {
        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['args']
            );
        }
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['args']
            );
        }
    }
}
