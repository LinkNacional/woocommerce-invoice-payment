<?php
namespace LknWc\WcInvoicePayment\Includes;

/**
 * Register all actions and filters for the plugin.
 *
 * @see       https://www.linknacional.com/
 * @since      1.0.0
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @author     Link Nacional
 */
final class WcPaymentInvoiceLoader {
    /**
     * The array of actions registered with WordPress.
     *
     * @since    1.0.0
     *
     * @var array the actions registered with WordPress to fire when the plugin loads
     */
    private $actions;

    /**
     * The array of filters registered with WordPress.
     *
     * @since    1.0.0
     *
     * @var array the filters registered with WordPress to fire when the plugin loads
     */
    private $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     *
     * @param string $hook          the name of the WordPress action that is being registered
     * @param object $component     a reference to the instance of the object on which the action is defined
     * @param string $callback      the name of the function definition on the $component
     * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1): void {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since    1.0.0
     *
     * @param string $hook          the name of the WordPress filter that is being registered
     * @param object $component     a reference to the instance of the object on which the filter is defined
     * @param string $callback      the name of the function definition on the $component
     * @param int    $priority      Optional. The priority at which the function should be fired. Default is 10.
     * @param int    $accepted_args Optional. The number of arguments that should be passed to the $callback. Default is 1
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1): void {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Register the filters and actions with WordPress.
     *
     * @since    1.0.0
     */
    public function run(): void {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @since    1.0.0
     *
     * @param array  $hooks         the collection of hooks that is being registered (that is, actions or filters)
     * @param string $hook          the name of the WordPress filter that is being registered
     * @param object $component     a reference to the instance of the object on which the filter is defined
     * @param string $callback      the name of the function definition on the $component
     * @param int    $priority      the priority at which the function should be fired
     * @param int    $accepted_args the number of arguments that should be passed to the $callback
     *
     * @return array the collection of actions and filters registered with WordPress
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }
}
