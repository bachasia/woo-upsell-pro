<?php
/**
 * Loader: centralises hook registration, fires them on run().
 *
 * @package WooUpsellPro
 */

namespace WooUpsellPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WUP_Loader
 *
 * Stores actions and filters then registers them with WordPress on run().
 */
class WUP_Loader {

    /** @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> */
    protected array $actions = [];

    /** @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}> */
    protected array $filters = [];

    /**
     * Add an action hook entry.
     */
    public function add_action(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    /**
     * Add a filter hook entry.
     */
    public function add_filter(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $accepted_args = 1
    ): void {
        $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
    }

    /**
     * Register all collected hooks with WordPress.
     */
    public function run(): void {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                [ $hook['component'], $hook['callback'] ],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
