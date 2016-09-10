<?php

defined( 'ABSPATH' ) or die();

/**
 * Logger for the Redirection plugin
 * https://sv.wordpress.org/plugins/redirection/
 */
if ( ! class_exists("Plugin_Redirection") ) {

	class Plugin_Redirection extends SimpleLogger {

		public $slug = __CLASS__;

		function getInfo() {

			$arr_info = array(
				"name" => "Redirection",
				"description" => _x("Text", "Logger: Redirection", "simple-history"),
				"name_via" => _x("In plugin Redirection", "Logger: Redirection", "simple-history"),
				"capability" => "manage_options",
				"messages" => array(
					'redirection_redirection_added' => _x( 'Added a redirection for URL "{source_url}"', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_edited' => _x( 'Edited the redirection for URL "{source_url}"', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_enabled' => _x( 'Enabled the redirection for {items_count} URL(s)', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_disabled' => _x( 'Disabled the redirection for {items_count} URL(s)"', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_removed' => _x( 'Removed redirection for {items_count} URL(s)"', "Logger: Redirection", 'simple-history' ),
				),
			);

			return $arr_info;

		}

		function loaded() {

			// Catch redirection create, enable, disable
			add_action( "admin_init", array( $this, "on_admin_init" ) );

			// Catch edit existing redirect
			add_action( "wp_ajax_red_redirect_save", array( $this, "on_edit_save_redirect" ) );

		} // loaded

		function on_edit_save_redirect() {

			/*
			Edit and save redirection
			{
			    "old": "\/my-edited-old-page-again\/",
			    "title": "",
			    "group_id": "1",
			    "target": "\/my-edited-new-page-again\/",
			    "action_code": "301",
			    "action": "red_redirect_save",
			    "id": "7",
			    "_wpnonce": "732a2bb825",
			    "_wp_http_referer": "\/wp-admin\/admin-ajax.php"
			}
			_wpnonce:abfaeae905
			_wp_http_referer:/wp-admin/admin-ajax.php
			*/
			$this->log_redirection_edit( $_REQUEST );

		}


		/**
		 * Check if request is an create or enable/disable redirection
		 */
		function on_admin_init() {

			$referer = wp_get_raw_referer();

			// We only continue if referer contains page=redirection.php
			if ( false === strpos( $referer, "page=redirection.php" ) ) {
				return;
			}

			$referer_parsed = parse_url( $referer );

			error_log( "-----" );
			// error_log( SimpleHistory::json_encode( $referer_parsed ) );
			error_log( SimpleHistory::json_encode( $_REQUEST ) );

			/*
			Create redirection
			{
				"source": "source yo",
				"match": "url",
				"red_action": "url",
				"target": "dest yo",
				"group_id": "1",
				"add": "Add Redirection",
				"group": "0",
				"action": "red_redirect_add",
				"_wpnonce": "cdadb5a4ca",
				"_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php"
			}
			*/
			if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "red_redirect_add" ) {
				$this->log_redirection_add( $_REQUEST );
			}

			/*
			Enable/disable single or multiple direction(s)
			{
				"page": "redirection.php",
				"_wpnonce": "290f261024",
				"_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php",
				"action": "enable", or "disable"
				"id": "0",
				"paged": "1",
				"item": [
					"3",
					"2",
					"1"
				],
				"action2": "-1"
			}
			*/
			if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "enable" ) {
				$this->log_redirection_enable_or_disable( $_REQUEST );
			} else if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "disable" ) {
				$this->log_redirection_enable_or_disable( $_REQUEST );
			}

			/*
			Delete item(s)
			{
			    "page": "redirection.php",
			    "edit": "4",
			    "_wpnonce": "290f261024",
			    "_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&edit=4",
			    "action": "delete",
			    "id": "0",
			    "paged": "1",
			    "item": [
			        "6"
			    ],
			    "action2": "-1"
			}
			*/
			if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "delete" ) {
				$this->log_redirection_delete( $_REQUEST );
			}

		}

		function log_redirection_delete( $req ) {
			
			$items = isset( $req["item"] ) ? (array) $req["item"] : array();

			$context = array(
				"items" => $items,
				"items_count" => count( $items )
			);

			$message_key = "redirection_redirection_removed";

			$this->infoMessage(
				$message_key, 
				$context
			);

		}

		function log_redirection_edit( $req ) {

			$context = array(
				"source_url" => isset( $req["old"] ) ? $req["old"] : null,
				"target_url" => isset( $req["target"] ) ? $req["target"] : null,
				"item_id" => isset( $req["id"] ) ? $req["id"] : null,
			);

			$message_key = "redirection_redirection_edited";

			$this->infoMessage(
				$message_key, 
				$context
			);

		}

		function log_redirection_enable_or_disable( $req ) {
			
			$message_key = $req["action"] == "enable" ? "redirection_redirection_enabled" : "redirection_redirection_disabled";
			
			$items = isset( $req["item"] ) ? (array) $req["item"] : array();

			$context = array(
				"items" => $items,
				"items_count" => count( $items )
			);

			$this->infoMessage(
				$message_key, 
				$context
			);

		}

		function log_redirection_add( $req ) {
			
			$source = isset( $req["source"] ) ? $req["source"] : null;
			$target = isset( $req["target"] ) ? $req["target"] : null;

			$context = array(
				"source_url" => $source,
				"target_url" => $target,
			);

			$this->infoMessage("redirection_redirection_added", $context);

		}

		/*

		Add group
		{
			"path": "\/wp-admin\/tools.php",
			"query": "page=redirection.php&sub=groups"
		}
		{
			"page": "redirection.php",
			"sub": "groups",
			"_wpnonce": "4cac237744",
			"_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&sub=groups",
			"name": "new group yo",
			"module_id": "1",
			"add": "Add"
		}

		Delete group(s)
		{
			"path": "\/wp-admin\/tools.php",
			"query": "page=redirection.php&sub=groups"
		}
		{
			"page": "redirection.php",
			"sub": "groups",
			"_wpnonce": "290f261024",
			"_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&sub=groups",
			"action": "-1",
			"id": "0",
			"paged": "1",
			"item": [
				"3",
				"2"
			],
			"action2": "delete"
		}

		Disable group(s)
		{
			"path": "\/wp-admin\/tools.php",
			"query": "page=redirection.php&sub=groups"
		}
		{
			"page": "redirection.php",
			"sub": "groups",
			"_wpnonce": "290f261024",
			"_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&sub=groups",
			"action": "disable",
			"id": "0",
			"paged": "1",
			"item": [
				"1"
			],
			"action2": "-1"
		}


		*/

	} // class

} // class exists
