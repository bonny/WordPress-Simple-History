<?php

use Simple_History\Helpers;
use Simple_History\Log_Query;
use Simple_History\Loggers\Logger;

class RestAPITest extends \Codeception\TestCase\WPTestCase {
	private $events_endpoint = '/simple-history/v1/events';

    public function test_events_endpoint_unauthorized() {
		$response = $this->dispatch_request( 'GET', $this->events_endpoint );
        
		$this->assertEquals( 401, $response->get_status(), 'Status from REST API should be 401 since we are not authenticated.' );
	}
	

	public function test_events_endpoint_authorized() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$response = $this->dispatch_request( 'GET', $this->events_endpoint );
        
		$this->assertEquals( 200, $response->get_status(), 'Status from REST API should be 200 since we are authenticated.' );
    }

	public function test_events_endpoint_authorized_data() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->factory->user->create( [ 'role' => 'editor' ] );

		$response = $this->dispatch_request( 
			'GET', 
			$this->events_endpoint, [
				'per_page' => 5,
			] 
		);
        
        // Check the response data.
        $data = $response->get_data();
		
        $this->assertNotEmpty( $data, 'REST API data should not be empty.' );

		$this->assertCount( 5, $data, 'REST API data should contain 5 items.' );

		$this->assertStringContainsString( 'Created user', $data[0]['message'], 'First message should contain "created user".' );
    }

    // Utility method to dispatch REST API requests
    private function dispatch_request( $method, $route, $params = [] ) {
        $request = new WP_REST_Request( $method, $route );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }
        return rest_do_request( $request );
    }
}
