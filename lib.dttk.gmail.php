<?php
	class Console{
		
		var $green  = "\x1b[38;5;70;3m";
		var $red    = "\x1b[38;5;160;3m";
		var $yellow = "\x1b[38;5;178;3m";
		var $blue	= "\x1b[38;5;27;3m";
		var $reset  = "\x1b[0m";

		function __construct(){
			return;
		}

		function info($text){
			echo $this->green.$text.$this->reset."\n";
		}

		function warn($text){
			echo $this->yellow.$text.$this->reset."\n";
		}

		function error($text){
			echo $this->red.$text.$this->reset."\n";
		}

		function read($prompt){
			echo $this->blue.$prompt.": ".$this->reset;
			$in = fopen("php://STDIN","r");
			$input = fread($in,4096);
			fclose($in);
			return $input;
		}
	}

	class httpClient{
		var $ua;

		function __construct($ua=null){
			if($ua!=null){
				$this->ua = $ua;
			}
		}

		function _post($url,$headers=null,$payload){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_POST, 1);
			// curl_setopt($ch, CURLOPT_HEADER, 1);
			if($headers != null){
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);	
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
    		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
			// curl_setopt($ch, CURLOPT_VERBOSE, true);
			curl_setopt($ch, CURLOPT_USERAGENT, $this->ua);
			$response = curl_exec($ch);
			curl_close ($ch);
			return $response;
		}
	}

	class GoogleOauth{
		
		var $user;
		var $client;
		var $token;
		var $scope;
		var $uri;
		var $url;
		var $code;
		var $httpc;

		var $uaversion="1.0";
		var $conffile;

		function __construct($scope=null){
			$this->uri 		= new stdClass;
			$this->url 		= new stdClass;
			$this->client 	= new stdClass;
			$this->token 	= new stdClass;
			if($scope != null){
				$this->scope 	= $scope;	
			}
			$this->httpc = new httpClient();
			$this->con 	 = new Console();
		}

		function parseConf(){
			if( file_exists($this->conffile) ){
				$c 	= file_get_contents($this->conffile);
				$cj = json_decode($c);
				
				$this->client->id 		= $cj->installed->client_id;
				$this->client->secret 	= $cj->installed->client_secret;
				$this->uri->auth 		= $cj->installed->auth_uri;
				$this->uri->token 		= $cj->installed->token_uri;
				$this->uri->redirect 	= $cj->installed->redirect_uris[0];

				if( $this->httpc->ua == null ){
					preg_match('/(.+)-.+/U',$cj->installed->project_id,$matches);	
					$this->httpc->ua 	= $matches[1]."/".$this->uaversion;
				}
				
				return true;
			}else{
				$this->con->error("Service Configuration Not Found");
				return false;
			}
		}

		function generateAuthURL(){
			$url = $this->uri->auth."?scope=".urlencode($this->scope)."&redirect_uri=".$this->uri->redirect."&client_id=".$this->client->id."&response_type=code";
			echo "\n";
			$this->con->info("Open The Given URL Below, with the Browser and Copy Response Code.");
			// $this->info("You'll be asked the CODE as many times as the registered accounts.");
			$this->con->warn("Make sure You logged in with proper user to the provider site.");
			echo "\n";
			echo $url."\n";
			echo "\n";
			$this->code = $this->con->read("Code");
		}

		function getTokens(){
			if($this->parseConf()){
				$this->generateAuthURL();
				
				$params = [
					"code" 			=> $this->code,
					"client_secret" => $this->client->secret,
					"client_id"		=> $this->client->id,
					"redirect_uri"	=> $this->uri->redirect,
					"grant_type"	=> "authorization_code",
				];

				$resp = $this->httpc->_post($this->uri->token,null,$params);
				$rd = json_decode($resp);
				if( $rd->error ){
					$this->con->error($rd->error_description);
				}else{
					$this->token->access  = $rd->access_token;
					$this->token->refresh = $rd->refresh_token;
					// write those into a file that can be included from Gmail class
					$gsc = '<?php'."\n";
					$gsc .= '$this->oauth->token->refresh=\''.$this->token->refresh.'\';'."\n";
					$gsc .= '$this->oauth->token->access=\''.$this->token->access.'\';'."\n";
					$gsc .= '?>';
					file_put_contents(".gsession", $gsc);
				}
			}
		}

		function refreshToken(){
			
			$this->parseConf();

			$params = [
				"client_secret" => $this->client->secret,
				"client_id"		=> $this->client->id,
				"refresh_token" => $this->token->refresh,
				"grant_type"	=> "refresh_token",
			];
			

			$resp = $this->httpc->_post($this->uri->token,null,$params);
			// echo $resp."\n";
			$rd = json_decode($resp);
			// print_r($rd);
			if( $rd->error ){
				$this->con->error($rd->error_description);
			}else{
				$this->token->access = $rd->access_token;
				// write those into a file that can be included from Gmail class
				$gsc = '<?php'."\n";
				$gsc .= '$this->oauth->token->refresh=\''.$this->token->refresh.'\';'."\n";
				$gsc .= '$this->oauth->token->access=\''.$this->token->access.'\';'."\n";
				$gsc .= '?>';
				file_put_contents(".gsession", $gsc);
			}
		}
	}

	class EmailFormatter{
		var $envelope=[];
		var $body;
		// var $from;

		function __construct(){
			$part["type"] = TYPEMULTIPART;
			$part["subtype"] = "mixed";
			$this->body[] = $part;
		}

		function From($address){
			$this->envelope["from"] = $address;
		}

		function Recipient($address){
			$this->envelope["to"] = $address;
		}

		function CC($address){
			$this->envelope["cc"] = $address;	
		}

		function BCC($address){
			$this->envelope["bcc"] = $address;	
		}

		function Subject($subject){
			$this->envelope["subject"] = $subject;	
		}

		function Text($text){
			$part["type"] = TYPETEXT;
			$part["subtype"] = "plain";
			$part["description"] = "mailtext";
			$part["contents.data"] = "$text\n\n\n\t";
			
			$this->body[] = $part;			
		}

		function addAttachment($filename){
			$fp = fopen($filename, "r");
			$contents = fread($fp, filesize($filename));
			fclose($fp);

			$part["type"] = TYPEAPPLICATION;
			$part["encoding"] = ENCBINARY;
			$part["subtype"] = "octet-stream";
			$part["description"] = basename($filename);
			$part['disposition.type'] = 'attachment';
			$part['disposition'] = array ('filename'=> basename($filename));
			$part['type.parameters'] = array('name' => basename($filename));
			$part["contents.data"] = $contents;

			$this->body[] = $part;
		}

		function bake(){
			return imap_mail_compose( $this->envelope, $this->body );
		}
	}

	class Gmail{

		function __construct(){
			$this->oauth = new GoogleOauth();
			$this->formatter = new EmailFormatter();

			if( file_exists(".gsession") ){
				include(".gsession");
			}
		}

		function send(){
			
			if($this->oauth->token->refresh == null ){
				$this->oauth->getTokens();
			}

			$mail = $this->formatter->bake();
			// echo $mail;

			$url = "https://www.googleapis.com/upload/gmail/v1/users/".$this->oauth->user."/messages/send";
			
			$headers = [
				'Content-Type: message/rfc822',
				'Content-Length: '.strlen($mail),
				'Authorization: Bearer '.$this->oauth->token->access,
			];

			$resp = $this->oauth->httpc->_post($url,$headers,$mail);
			$rd = json_decode($resp);
			// print_r($rd);
			
			if( $rd->error->code == "401" ){
				echo "\n";
				$this->oauth->con->error($rd->error->message);
				$this->oauth->con->info("Token Refresh Needed.");
				$this->oauth->refreshToken();
				$this->oauth->con->info("Resending...");
				$this->send();
			}
			
			return true;
		}
	}
?>