<?php
/**
 * REST API Endpoints
 *
 * @package    SEOAnalyticsPro
 * @subpackage SEOAnalyticsPro/includes/api
 */

class SAP_REST_API {

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'sap/v1';

        // Projects endpoints
        register_rest_route($namespace, '/projects', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_projects'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_project'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));

        register_rest_route($namespace, '/projects/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_project'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_project'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_project'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));

        // Keywords endpoints
        register_rest_route($namespace, '/projects/(?P<project_id>\d+)/keywords', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_keywords'),
            'permission_callback' => array($this, 'check_permissions')
        ));

        // Analysis endpoint
        register_rest_route($namespace, '/analyze/keyword', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'analyze_keyword'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }

    /**
     * Check user permissions
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }

    /**
     * Get projects
     */
    public function get_projects($request) {
        return rest_ensure_response(array('message' => 'Projects endpoint'));
    }

    /**
     * Get single project
     */
    public function get_project($request) {
        return rest_ensure_response(array('message' => 'Project endpoint'));
    }

    /**
     * Create project
     */
    public function create_project($request) {
        return rest_ensure_response(array('message' => 'Create project'));
    }

    /**
     * Update project
     */
    public function update_project($request) {
        return rest_ensure_response(array('message' => 'Update project'));
    }

    /**
     * Delete project
     */
    public function delete_project($request) {
        return rest_ensure_response(array('message' => 'Delete project'));
    }

    /**
     * Get keywords
     */
    public function get_keywords($request) {
        return rest_ensure_response(array('message' => 'Keywords endpoint'));
    }

    /**
     * Analyze keyword
     */
    public function analyze_keyword($request) {
        return rest_ensure_response(array('message' => 'Analyze keyword'));
    }
}
