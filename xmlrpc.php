<?php
	/**************************************************************************\
	* phpGroupWare xmlrpc server                                               *
	* http://www.phpgroupware.org                                              *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	/* $Source$ */

	// NOTE! This file is still in the experimental stages, use at your own risk!
	// The only current documentation for it is the code and the comments
	// A document explaining its usage should be done shortly
	// PLEASE, do *NOT* make any changes to this file without talking to me
	// directly first.  Until I get it fully undercontrol.
	// There might be possiable security holes in this, I haven't fully tested it
	// (jengo)

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'currentapp'            => 'login',
		'noheader'              => True,
		'disable_Template_class' => True
	);
	include('header.inc.php');

	// If XML-RPC isn't enabled in PHP, return an XML-RPC response stating so
	if (! function_exists('xmlrpc_server_create'))
	{
		echo "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n";
		echo "<methodResponse>\n";
		echo "<fault>\n";
		echo " <value>\n";
		echo "  <struct>\n";
		echo "   <member>\n";
		echo "    <name>faultString</name>\n";
		echo "    <value>\n";
		echo "     <string>XML-RPC support NOT enabled in PHP installation</string>\n";
		echo "    </value>\n";
		echo "   </member>\n";
		echo "   <member>\n";
		echo "    <name>faultCode</name>\n";
		echo "    <value>\n";
		echo "     <int>1005</int>\n";
		echo "    </value>\n";
		echo "   </member>\n";
		echo "  </struct>\n";
		echo " </value>\n";
		echo "</fault>\n";
		echo "</methodResponse>\n";

		exit;
	}


	// Return all PHP errors as faults
	$GLOBALS['xmlrpc_server'] = xmlrpc_server_create();
	error_reporting(E_ERROR | E_WARNING | E_PARSE);
	set_error_handler('xmlrpc_custom_error');

	$headers = getallheaders();
	if (ereg('Basic',$headers['Authorization']))
	{
		$tmp  = $headers['Authorization'];
		$tmp  = ereg_replace(' ','',$tmp);
		$tmp  = ereg_replace('Basic','',$tmp);
		$auth = base64_decode(trim($tmp));
		list($sessionid,$kp3) = split(':',$auth);

		if ($GLOBALS['phpgw']->session->verify($sessionid,$kp3))
		{
			$GLOBALS['xmlrpc_server'] = xmlrpc_server_create();
			$request_xml              = $HTTP_RAW_POST_DATA;

			// Find out what method they are calling
			// This function is odd, you *NEED* to assign the results
			// to a value, or $method is never returned.  (jengo)
			$null = xmlrpc_decode_request($request_xml, &$method);
			$GLOBALS['phpgw']->session->xmlrpc_method_called = $method;
			$GLOBALS['phpgw']->session->update_dla();

			// Check permissions and load the class, register all methods
			// for that class, and execute it
			list($app,$class,$func) = explode('.',$method);

			if ($method == 'system.logout' || $GLOBALS['phpgw_info']['user']['apps'][$app] || $app == 'phpgwapi')
			{
				$GLOBALS['obj'] = CreateObject($app . '.' . $class);

				xmlrpc_server_register_method($xmlrpc_server,sprintf('%s.%s.%s',$app,$class,'listMethods'),'xmlrpc_list_methods');
				xmlrpc_server_register_method($xmlrpc_server,sprintf('%s.%s.%s',$app,$class,'describeMethods'),xmlrpc_describe_methods);
				xmlrpc_server_register_method($xmlrpc_server,'system.logout','xmlrpc_logout');

				while (list(,$new_method) = @each($obj->xmlrpc_methods))
				{
					$full_method_name = sprintf('%s.%s.%s',$app,$class,$new_method['name']);

					xmlrpc_server_register_method($xmlrpc_server,$full_method_name,'xmlrpc_call_wrapper');
					// The following function is listed as being in the API, but doesn't actually exisit.
					// This is more of a mental note to track down its exisitence
					//xmlrpc_server_set_method_description($xmlrpc_server,$full_method_name,$new_method);
				}
			}
			else if ($method != 'system.listMethods' && $method != 'system.describeMethods')
			{
				xmlrpc_error(1001,'Access not permitted');
			}

			echo xmlrpc_server_call_method($xmlrpc_server,$request_xml,'');
			xmlrpc_server_destroy($xmlrpc_server);
		}
		else
		{
			// Session is invailed
			xmlrpc_error(1001,'Session expired');
		}
	}
	else
	{
		// First, create a single method being system.login
		// If they don't request this, then just return a failed session error
		$xmlrpc_server = xmlrpc_server_create();
		$request_xml   = $HTTP_RAW_POST_DATA;

		// Find out what method they are calling
		// This function is odd, you *NEED* to assign the results
		// to a value, or $method is never returned.  (jengo)
		$null = xmlrpc_decode_request($request_xml, &$method);

		if ($method == 'system.login')
		{
			xmlrpc_server_register_method($xmlrpc_server,'system.login','xmlrpc_login');
			echo xmlrpc_server_call_method($xmlrpc_server,$request_xml,'');
			xmlrpc_server_destroy($xmlrpc_server);

			exit;
		}
		else
		{
			// They didn't request system.login and they didn't pass sessionid or
			// kp3, this is an invailed session (The session could have also been killed or expired)
			xmlrpc_error(1001,'Session expired');
		}
	}

	// When PHP returns an error, return that error with a fault instead of
	// HTML with will make most parsers fall apart
	function xmlrpc_custom_error($error_number, $error_string, $filename, $line, $vars)
	{
		if (error_reporting() & $error_number)
		{
			$error_string .= sprintf("\nFilename: %s\nLine: %s",$filename,$line);

			xmlrpc_error(1005,$error_string);
		}
	}

	// This will create an XML-RPC error
	// FIXME!  This needs to be expanded to handle PHP errors themselfs
	//         it will make debugging easier
	function xmlrpc_error($error_number, $error_string)
	{
		$values = array(
			'faultString' => $error_string,
			'faultCode'   => $error_number
		);

		echo xmlrpc_encode_request(NULL,$values);

		xmlrpc_server_destroy($GLOBALS['xmlrpc_server']);
		exit;
	}

	// This will dynamicly create the avaiable methods for each class
	function xmlrpc_list_methods($method)
	{
		list($app,$class,$func) = explode('.',$method);
		$methods[] = 'system.login';
		$methods[] = 'system.logout';
		$methods[] = $method;
		$methods[] = $app . '.' . $class . 'describeMethods';
		for ($i=0; $i<count($GLOBALS['obj']->xmlrpc_methods); $i++)
		{
			$methods[] = $GLOBALS['obj']->xmlrpc_methods[$i]['name'];
		}

		return $methods;
	}

	function xmlrpc_describe_methods($method)
	{
		list($app,$class,$func) = explode('.',$method);
		// FIXME! Add the missing pre-defined methods, example: system.login
		for ($i=0; $i<count($GLOBALS['obj']->xmlrpc_methods); $i++)
		{
			$methods[] = $GLOBALS['obj']->xmlrpc_methods[$i];
		}

		return $methods;
	}

	// I know everyone hates wrappers, but this is the best way this can be done
	// The XML-RPC functions pass method_name as the first parameter, which is
	// unacceptable.
	// Another reason for this, is it might be possiable to pass the sessionid
	// and kp3 instead of using HTTP_AUTH features.
	// Would be a nice workaround for librarys that don't support it, as its
	// not in the XML-RPC spec.
	function xmlrpc_call_wrapper($method_name, $parameters)
	{
		$a = explode('.',$method_name);

		if (count($parameters) == 0)
		{
			$return = $GLOBALS['obj']->$a[2]();
		}
		else if (count($parameters) == 1)
		{
			$return = $GLOBALS['obj']->$a[2]($parameters[0]);
		}
		else
		{
			for ($i=0; $i<count($parameters); $i++)
			{
				$p[] = '$parameters[' . $i . ']';
			}
			eval('$return = $GLOBALS[\'obj\']->$a[2](' . implode(',',$p) . ');');
		}

		// This needs to be expanded and more fully tested
		if (gettype($return) == 'NULL')
		{
			return xmlrpc_error(1002,'No return value detected');
		}
		else
		{
			return $return;
		}
	}

	// The following are common functions used ONLY by XML-RPC
	function xmlrpc_login($method_name, $parameters)
	{
		$p = $parameters[0];

		if ($p['domain'])
		{
			$username = $p['username'] . '@' . $p['domain'];
		}
		else
		{
			$username = $p['username'];
		}

		$sessionid = $GLOBALS['phpgw']->session->create($username,$p['password'],'text');
		$kp3       = $GLOBALS['phpgw']->session->kp3;
		$domain    = $GLOBALS['phpgw']->session->account_domain;

		if ($sessionid && $kp3)
		{
			return array(
				'sessionid' => $sessionid,
				'kp3'       => $kp3,
				'domain'    => $domain
			);
		}
		else
		{
			xmlrpc_error(1001,'Login failed');
		}
	}

	function xmlrpc_logout($method, $parameters)
	{
		// We have already verified the session upon before this method is even created
		// As long as nothing happens upon, its safe to destroy the session without
		// fear of it being a hijacked session
		$GLOBALS['phpgw']->session->destroy($GLOBALS['phpgw']->session->sessionid,$GLOBALS['phpgw']->session->kp3);

		return True;
	}


