<?php
class AWS
{
	
	/* Change keys below
	---------------------------------------------------------------------- */
	var $access_key_id = "YOUR ACCESS KEY ID HERE";
	var $secret_access_key = "YOUR SECRET ACCESS KEY HERE";
	/* Stop editing here */
	
	var $amazon_post_url = "cloudfront.amazonaws.com";
	var $amazon_post_path = "/2008-06-30/distribution";
	var $xml = array();
	var $data = array();
	var $debug = false;
	var $errors = array();
	var $timezone = "";
	var $etag = "";
	var $distribution_id = "";
	var $pending_deletes = array();
	
	function AWS()
	{
		if(!empty($this->timezone))
		{
			date_default_timezone_set("GMT"); // this doesn't appear work inside a class, leaving it here for now
		}
		
		if(!is_array(@$_SESSION['pending_deletes']))
		{
			$_SESSION['pending_deletes'] = array();
		}
	}
	
	function create_distribution($bucket,$comment='')
	{
		$this->xml[] = '<?xml version="1.0" encoding="UTF-8"?>'."\r\n";
		$this->xml[] = '<DistributionConfig xmlns="http://cloudfront.amazonaws.com/doc/2008-06-30/">'."\r\n";
		$this->xml[] = "\t".'<Origin>'.$bucket.'.s3.amazonaws.com</Origin>'."\r\n";
		$this->xml[] = "\t".'<CallerReference>'.$this->_makeref().'</CallerReference>'."\r\n";
		if(!empty($comment))
		{
			$this->xml[] = "\t".'<Comment>'.htmlentities($comment).'</Comment>'."\r\n";
		}
		$this->xml[] = "\t".'<Enabled>true</Enabled>'."\r\n";
		$this->xml[] = '</DistributionConfig>'."\r\n";
		
		$data = implode($this->xml);
		
		if($this->debug)
		{
			echo "<strong>Creating distribution</strong>\n".htmlentities($data)."\n";
		}

		return $this->_create($this->amazon_post_url,$this->amazon_post_path,$data);
	}
	
	function list_distributions()
	{	
		if($this->debug)
		{
			echo "<strong>Getting distribution</strong>\n";
		}

		$response = $this->_list();
		
		$headers = $this->http_parse_headers($response);
		
		$s = simplexml_load_string($headers['status']);
		
		$return = array();
		foreach($s->DistributionSummary as $row)
		{
			$return[] = $row;
		}
		
		return $return;
		
	}
	
	function get_config($distribution_id)
	{	
		if($this->debug)
		{
			echo "<strong>Getting distribution information</strong>\n";
		}

		$response = $this->_getconfig($distribution_id);
		
		$headers = $this->http_parse_headers($response);
		
		$s = simplexml_load_string($headers['status']);
		
		if(isset($headers['ETag']))
		{
			$this->etag = $headers['ETag'];
		}
		
		return $s;
	}
	
	function get_info($distribution_id)
	{	
		if($this->debug)
		{
			echo "<strong>Getting distribution information</strong>\n";
		}

		$response = $this->_getinfo($distribution_id);
		
		$headers = $this->http_parse_headers($response);
		
		$s = simplexml_load_string($headers['status']);
		
		if(isset($headers['ETag']))
		{
			$this->etag = $headers['ETag'];
		}
		
		return $s;
		
	}
	
	function disable_distribution($bucket,$distribution_id,$etag,$caller_reference)
	{
		$this->xml = array();
		
		$this->xml[] = '<?xml version="1.0" encoding="UTF-8"?>'."\r\n";
		$this->xml[] = '<DistributionConfig xmlns="http://cloudfront.amazonaws.com/doc/2008-06-30/">'."\r\n";
		$this->xml[] = "\t".'<Origin>'.$bucket.'</Origin>'."\r\n";
		$this->xml[] = "\t".'<CallerReference>'.$caller_reference.'</CallerReference>'."\r\n";
		$this->xml[] = "\t".'<Enabled>false</Enabled>'."\r\n";
		$this->xml[] = '</DistributionConfig>'."\r\n";
		
		$data = implode($this->xml);
		
		$response = $this->_putinfo($distribution_id,$etag,$data);
		
		$headers = $this->http_parse_headers($response);
		
		$s = simplexml_load_string($headers['status']);
		
		$this->etag = $headers['ETag'];
		$this->distribution_id = $distribution_id;
		
		// we have to disable it, wait for that request to finish, then we can delete it
		// so we just update the "pending updates" array so that we can use ajax to periodically
		// check it's status and delete it when it's done
		
		$pending['distribution_id'] = $this->distribution_id;
		$pending['etag'] = $this->etag;
		
		$_SESSION['pending_deletes'][] = $pending;
		
		#$this->delete_distribution();
		
		return true;
	}
	
	function delete_distribution($distribution_id,$etag)
	{
		return $this->_delete($distribution_id,$etag);
	}
	
	function _makeref()
	{
		return md5(uniqid(rand(),true));
	}
	
	function _delete($distribution_id,$etag)
	{
		$sent = "";
		
		$date = utf8_encode(date("D, j M Y H:i:s")." GMT");		
		
		$key = $this->_makekey($date);
		
		$method = 'DELETE';
		$fp = fsockopen("ssl://".$this->amazon_post_url, 443, $errno, $errstr, $timeout = 30);
		
		if(!$fp)
		{
			if($this->debug)
			{
				echo "Problem connecting to AWS<br>\n";
			}
			$this->errors[] = "Problem connecting to AWS";
			return false;
		}
		else
		{
			fputs($fp, "$method /2008-06-30/distribution/$distribution_id HTTP/1.1\r\n");
			fputs($fp, "Host: $this->amazon_post_url\r\n");
			fputs($fp, "If-Match: $etag\r\n");
			fputs($fp, "Date: ".$date."\r\n");
			fputs($fp, "x-amz-date: ".$date."\r\n");
			fputs($fp, "Authorization: $key\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			while (!feof($fp)) {
				$sent .= fgets($fp,128);
			}
			fclose($fp);
		}
		
		if($this->debug)
		{
			echo "<strong>Response:</strong>\n";
			echo htmlentities($sent);
		}
		
		return $sent;
	}
	
	function _create($host,$path,$data)
	{	
		$sent = "";
		
		$date = utf8_encode(date("D, j M Y H:i:s")." GMT");		
		
		$key = $this->_makekey($date);
		
		$method = 'POST';
		$fp = fsockopen("ssl://".$host, 443, $errno, $errstr, $timeout = 30);
		
		if(!$fp)
		{
			if($this->debug)
			{
				echo "Problem connecting to AWS<br>\n";
			}
			$this->errors[] = "Problem connecting to AWS";
			return false;
		}
		else
		{
			fputs($fp, "$method $path HTTP/1.1\r\n");
			fputs($fp, "Host: $host\r\n");
			fputs($fp, "Authorization: $key\r\n");
			fputs($fp, "Date: ".$date."\r\n");
			fputs($fp, "x-amz-date: ".$date."\r\n");
			fputs($fp, "Content-Type: text/xml;\r\n");
			fputs($fp, "Content-length: " . strlen($data) . "\r\n\r\n");
			fputs($fp, $data."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			while (!feof($fp)) {
				$sent .= fgets($fp,128);
			}
			fclose($fp);
		}
		
		if($this->debug)
		{
			echo "<strong>Response:</strong>\n";
			echo htmlentities($sent);
		}
		
		return $sent;
	}
	
	function _list()
	{
		$sent = "";
		
		$date = utf8_encode(date("D, j M Y H:i:s")." GMT");		
		
		$key = $this->_makekey($date);
		
		$method = 'GET';
		$fp = fsockopen("ssl://".$this->amazon_post_url, 443, $errno, $errstr, $timeout = 30);
		
		if(!$fp)
		{
			if($this->debug)
			{
				echo "Problem connecting to AWS<br>\n";
			}
			$this->errors[] = "Problem connecting to AWS";
			return false;
		}
		else
		{
			fputs($fp, "$method $this->amazon_post_path HTTP/1.1\r\n");
			fputs($fp, "Host: $this->amazon_post_url\r\n");
			fputs($fp, "Authorization: $key\r\n");
			fputs($fp, "Date: ".$date."\r\n");
			fputs($fp, "x-amz-date: ".$date."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			while (!feof($fp)) {
				$sent .= fgets($fp,128);
			}
			fclose($fp);
		}
		
		if($this->debug)
		{
			echo "<strong>Response:</strong>\n";
			echo htmlentities($sent);
		}
		
		return $sent;
	}
	
	function _getconfig($distribution_id)
	{
		$sent = "";
		
		$date = utf8_encode(date("D, j M Y H:i:s")." GMT");		
		
		$key = $this->_makekey($date);
		
		$method = 'GET';
		$fp = fsockopen("ssl://".$this->amazon_post_url, 443, $errno, $errstr, $timeout = 30);
		
		if(!$fp)
		{
			if($this->debug)
			{
				echo "Problem connecting to AWS<br>\n";
			}
			$this->errors[] = "Problem connecting to AWS";
			return false;
		}
		else
		{
			fputs($fp, "$method /2008-06-30/distribution/$distribution_id/config HTTP/1.1\r\n");
			fputs($fp, "Host: $this->amazon_post_url\r\n");
			fputs($fp, "Authorization: $key\r\n");
			fputs($fp, "Date: ".$date."\r\n");
			fputs($fp, "x-amz-date: ".$date."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			while (!feof($fp)) {
				$sent .= fgets($fp,128);
			}
			fclose($fp);
		}
		
		if($this->debug)
		{
			echo "<strong>Response:</strong>\n";
			echo htmlentities($sent);
		}
		
		return $sent;
	}
	
	function _getinfo($distribution_id)
	{
		$sent = "";
		
		$date = utf8_encode(date("D, j M Y H:i:s")." GMT");		
		
		$key = $this->_makekey($date);
		
		$method = 'GET';
		$fp = fsockopen("ssl://".$this->amazon_post_url, 443, $errno, $errstr, $timeout = 30);
		
		if(!$fp)
		{
			if($this->debug)
			{
				echo "Problem connecting to AWS<br>\n";
			}
			$this->errors[] = "Problem connecting to AWS";
			return false;
		}
		else
		{
			fputs($fp, "$method /2008-06-30/distribution/$distribution_id HTTP/1.1\r\n");
			fputs($fp, "Host: $this->amazon_post_url\r\n");
			fputs($fp, "Authorization: $key\r\n");
			fputs($fp, "Date: ".$date."\r\n");
			fputs($fp, "x-amz-date: ".$date."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			while (!feof($fp)) {
				$sent .= fgets($fp,128);
			}
			fclose($fp);
		}
		
		if($this->debug)
		{
			echo "<strong>Response:</strong>\n";
			echo htmlentities($sent);
		}
		
		return $sent;
	}
	
	function _putinfo($distribution_id,$etag,$data)
	{
		$sent = "";
		
		$date = utf8_encode(date("D, j M Y H:i:s")." GMT");		
		
		$key = $this->_makekey($date);
		
		$method = 'PUT';
		$fp = fsockopen("ssl://".$this->amazon_post_url, 443, $errno, $errstr, $timeout = 30);
		
		if(!$fp)
		{
			if($this->debug)
			{
				echo "Problem connecting to AWS<br>\n";
			}
			$this->errors[] = "Problem connecting to AWS";
			return false;
		}
		else
		{
			fputs($fp, "$method /2008-06-30/distribution/$distribution_id/config HTTP/1.1\r\n");
			fputs($fp, "Host: $this->amazon_post_url\r\n");
			fputs($fp, "Authorization: $key\r\n");
			fputs($fp, "If-Match: $etag\r\n");
			fputs($fp, "Date: ".$date."\r\n");
			fputs($fp, "x-amz-date: ".$date."\r\n");
			fputs($fp, "Content-Type: text/xml;\r\n");
			fputs($fp, "Content-length: " . strlen($data) . "\r\n\r\n");
			fputs($fp, $data."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			while (!feof($fp)) {
				$sent .= fgets($fp,128);
			}
			fclose($fp);
		}
		
		if($this->debug)
		{
			echo "<strong>Response:</strong>\n";
			echo htmlentities($sent);
		}
		
		return $sent;
	}
	
	function _makekey($date)
	{
		return "AWS" . " " . $this->access_key_id . ":" . base64_encode($this->_hmacsha1($this->secret_access_key,$date));
	}
	
	function _hmacsha1($key,$data) {
	    $blocksize=64;
	    $hashfunc='sha1';
	    if (strlen($key)>$blocksize)
	        $key=pack('H*', $hashfunc($key));
	    $key=str_pad($key,$blocksize,chr(0x00));
	    $ipad=str_repeat(chr(0x36),$blocksize);
	    $opad=str_repeat(chr(0x5c),$blocksize);
	    $hmac = pack(
	                'H*',$hashfunc(
	                    ($key^$opad).pack(
	                        'H*',$hashfunc(
	                            ($key^$ipad).$data
	                        )
	                    )
	                )
	            );
	    return $hmac;
	}
	
	function http_parse_headers($headers=false){
	    if($headers === false){
	        return false;
	        }
	    $headers = str_replace("\r","",$headers);
	    $headers = explode("\n",$headers);
	    foreach($headers as $value){
	        $header = explode(": ",$value);
	        if(@$header[0] && !@$header[1]){
	            $headerdata['status'] = $header[0];
	            }
	        elseif($header[0] && $header[1]){
	            $headerdata[$header[0]] = $header[1];
	            }
	        }
	    return $headerdata;
	    }
}
?>