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
					'redirection_redirection_edited' => _x( 'Edited the redirection for URL "{source_url}', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_enabled' => _x( 'Enabled the redirection for {items_count} URL(s)', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_disabled' => _x( 'Disabled the redirection for {items_count} URL(s)', "Logger: Redirection", 'simple-history' ),
					'redirection_redirection_removed' => _x( 'Removed redirection for {items_count} URL(s)', "Logger: Redirection", 'simple-history' ),
					'redirection_options_saved' => _x( 'Updated options', "Logger: Redirection", 'simple-history' ),
					'redirection_options_removed_all' => _x( 'Removed all options and deactivated plugin', "Logger: Redirection", 'simple-history' ),
					'redirection_group_added' => _x( 'Added group "{group_name}"', "Logger: Redirection", 'simple-history' ),
					'redirection_group_deleted' => _x( 'Deleted {items_count} group(s)', "Logger: Redirection", 'simple-history' ),
				),
				/*
				"labels" => array(
					"search" => array(
						"label" => _x("Plugin Redirection", "Logger: Redirection", "simple-history"),
						"label_all" => _x("All posts & pages activity", "Logger: Redirection", "simple-history"),
						"options" => array(
							_x("Posts created", "Logger: Redirection", "simple-history") => array(
								"post_created"
							),
							_x("Posts updated", "Logger: Redirection", "simple-history") => array(
								"post_updated"
							),
							_x("Posts trashed", "Logger: Redirection", "simple-history") => array(
								"post_trashed"
							),
							_x("Posts deleted", "Logger: Redirection", "simple-history") => array(
								"post_deleted"
							),
							_x("Posts restored", "Logger: Redirection", "simple-history") => array(
								"post_restored"
							),
						)
					) // end search array
				) // end labels
				*/

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
				return;
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
			if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "enable" && empty( $_REQUEST["sub"] ) ) {
				$this->log_redirection_enable_or_disable( $_REQUEST );
				return;
			} else if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "disable" && empty( $_REQUEST["sub"] )) {
				$this->log_redirection_enable_or_disable( $_REQUEST );
				return;
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
			if ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "delete" && empty( $_REQUEST["sub"] ) ) {
				$this->log_redirection_delete( $_REQUEST );
				return;
			}

			/*
			Options
			- delete all options and deactivate plugin
			{
			    "page": "redirection.php",
			    "sub": "options",
			    "_wpnonce": "e2c008ca25",
			    "_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&sub=options",
			    "delete": "Delete"
			}
			*/
			if ( isset( $_REQUEST["sub"] ) && $_REQUEST["sub"] == "options" && isset( $_REQUEST["delete"] ) && $_REQUEST["delete"] == "Delete" ) {
				$this->log_options_delete_all( $_REQUEST );
				return;
			}

			/*
			Save options {
			    "page": "redirection.php",
			    "sub": "options",
			    "_wpnonce": "8fe9b57662",
			    "_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&sub=options",
			    "support": "on",
			    "expire_redirect": "7",
			    "expire_404": "7",
			    "monitor_post": "0",
			    "token": "acf88715b12038e3aca1ae1b3d82132a",
			    "auto_target": "",
			    "update": "Update"
			}
			*/
			if ( 
				isset( $_REQUEST["sub"] ) && $_REQUEST["sub"] == "options" &&
				isset( $_REQUEST["update"] ) && $_REQUEST["update"] == "Update" 

			) {
				$this->log_options_save( $_REQUEST );
				return;
			}

			/*
			Add group
			{
				"page": "redirection.php",
				"sub": "groups",
				"_wpnonce": "4cac237744",
				"_wp_http_referer": "\/wp-admin\/tools.php?page=redirection.php&sub=groups",
				"name": "new group yo",
				"module_id": "1",
				"add": "Add"
			}
			*/
			if ( 
				isset( $_REQUEST["sub"] ) && $_REQUEST["sub"] == "groups" &&
				isset( $_REQUEST["add"] ) && $_REQUEST["add"] == "Add"	
			) {
				$this->log_group_add( $_REQUEST );
				return;
			}


			/*
			Delete group(s)
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
			*/
			if ( 
				isset( $_REQUEST["sub"] ) && $_REQUEST["sub"] == "groups" &&
				isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "delete"	
			) {
				$this->log_group_delete( $_REQUEST );
				return;
			}

			/*
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
			if ( 
				isset( $_REQUEST["sub"] ) && $_REQUEST["sub"] == "groups" &&
				isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "enable"	
			) {
				$this->log_group_enable_or_disable( $_REQUEST );
				return;
			} else  if ( 
				isset( $_REQUEST["sub"] ) && $_REQUEST["sub"] == "groups" &&
				isset( $_REQUEST["action"] ) && $_REQUEST["action"] == "disable"	
			) {
				$this->log_group_enable_or_disable( $_REQUEST );
				return;
			}

		} // on admin init


		function log_group_enable_or_disable() {
			// @HERE
		}

		function log_group_delete( $req ) {

			$items = isset( $req["item"] ) ? (array) $req["item"] : array();

			$context = array(
				"items" => $items,
				"items_count" => count( $items ),
			);

			$this->infoMessage(
				"redirection_group_deleted",
				$context
			);

		}

		function log_group_add( $req ) {

			$group_name = isset( $req["name"] ) ? $req["name"] : null;

			if ( ! $group_name ) {
				return;
			}

			$context = array(
				"group_name" => $group_name
			);

			$this->infoMessage(
				"redirection_group_added",
				$context
			);

		}

		function log_options_save( $req ) {

			$this->infoMessage("redirection_options_saved");		 

		}

		function log_options_delete_all( $req ) {

			$this->infoMessage("redirection_options_removed_all");

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

			/*
			log_redirection_edit
			{
			    "old": "ddd changedaa",
			    "regex": "on",
			    "title": "this is descriptionaa",
			    "group_id": "12",
			    "user_agent": "Firefoxaa",
			    "url_from": "eee changedaa",
			    "url_notfrom": "not matched straa",
			    "action": "red_redirect_save",
			    "id": "7",
			    "_wpnonce": "f15cdcdaea",
			    "_wp_http_referer": "\/wp-admin\/admin-ajax.php"
			}
			*/
			#error_log( "log_redirection_edit\n" . SimpleHistory::json_encode( $_REQUEST ) );

			$context = array(
				"source_url"  => isset( $req["old"] ) ? $req["old"] : null,
				"target_url"  => isset( $req["target"] ) ? $req["target"] : null,
				"item_id"     => isset( $req["id"] ) ? $req["id"] : null,
				"title"       => isset( $req["title"] ) ? $req["title"] : null,
				"regex"       => isset( $req["regex"] ) ? true : false,
				"group_id"    => isset( $req["group_id"] ) ? $req["group_id"] : null,
				"user_agent"  => isset( $req["user_agent"] ) ? $req["user_agent"] : null,
				"url_from"    => isset( $req["url_from"] ) ? $req["url_from"] : null,
				"url_notfrom" => isset( $req["url_notfrom"] ) ? $req["url_notfrom"] : null,
				"action_code" => isset( $req["action_code"] ) ? $req["action_code"] : null,
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
			
			if ( ! isset( $req["group_id"] ) ) {
				return;
			}
			
			$source = isset( $req["source"] ) ? $req["source"] : null;
			$target = isset( $req["target"] ) ? $req["target"] : null;
			$match = isset( $req["match"] ) ? $req["match"] : null;
			$action = isset( $req["action"] ) ? $req["action"] : null;
			$group_id = isset( $req["group_id"] ) ? $req["group_id"] : null;
			$regex = isset( $req["regex"] ) ? true : false;

			$context = array(
				"source_url" => $source,
				"target_url" => $target,
				"match" => $match,
				"action" => $action,
				"group_id" => $group_id,
				"regex" => $regex,
			);

			$this->infoMessage("redirection_redirection_added", $context);

		}

	} // class

} // class exists
