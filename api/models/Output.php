<?php
class WERealtime_Model_Output {
	protected static $_instance = null;
	
	public static function getInstance() {
		if (self::$_instance === null) {
			self::$_instance = new self ();
		}
		return self::$_instance;
	}
	
	function output($result, $format = '') {
		switch ($format) {
			case 'GeoRSS':
				echo $this->georss($result);
				break;
			case 'json':
			case 'JSON':
				echo json_encode($result);
				break;
			case 'text':
				echo $result;
				break;
			case 'TAB':
				header('Content-Type: text/plain');
				echo ML::response_text($result);
				break;
			case 'XML':
				echo $this->xml($result);
				break;
			case 'RSS':
				echo ML::response_rss($result);
				break;
			case 'PHP Serialized':
				echo serialize($result);
				break;
			case 'pre':
				header('Content-Type: text/plain');
				print_r($result);
				break;
			default:
		}
	}
	
	function response_text($rows) {
		$lines = array();
		
		if (is_array($rows)) {
			foreach ($rows as $i => $row) {
				$line = array();
				if ($i == 0) {
					foreach ($row as $field => $col) {
						$line[] = str_replace("\t", '\t', $field);
					}
					$lines[] = implode("\t", $line);
				}
				
				$line = array();
				foreach ($row as $col) {
					$line[] = str_replace("\t", '\t', $col);
				}
				$lines[] = implode("\t", $line);
			}
		} else {
			return $rows;
		}
		
		return implode("\n", $lines);
	}
	
	function xml($result) {
		header('Content-Type: text/xml');
		$dom = new DOMDocument("1.0");
		
		$resultNode = $dom->createElement('result');
		$dom->appendChild($resultNode);
		
		$this->_xml_recursive($dom, $resultNode, $result);
		echo $dom->saveXML();
	}
	private function _xml_recursive(&$dom, &$currentNode, &$result) {
		if (is_array($result)) {
			$i = 0; $is_hash = false;
			foreach ($result as $key => $val) {
				if ($key != $i++) {
					$is_hash = true;
					break;
				}
			}
			
			if ($is_hash) {
				foreach ($result as $key => $val) {
					if (preg_match('/^[^a-zA-Z_]/', $key)) {
						$key = 'key-' . $key;
					}
					$node = $dom->createElement($key);
					$currentNode->appendChild($node);
					
					$this->_xml_recursive($dom, $node, $result[$key]);
				}
			} else {
				foreach ($result as $val) {
					$item = $dom->createElement('item');
					$currentNode->appendChild($item);
					
					$this->_xml_recursive($dom, $item, $val);
				}
			}
		} else {
			$textNode = $dom->createTextNode($result);
			if (!is_string($result)) {
				$currentNode->setAttribute('type', gettype($result));
			}
			$currentNode->appendChild($textNode);
		}
	}
	
	function georss($result) {
		header('Content-Type: text/xml');
		$dom = new DOMDocument("1.0");
		
		$rssNode = $dom->createElement('rss');
		$rssNode->setAttribute('xmlns:georss', "http://www.georss.org/georss");
		$dom->appendChild($rssNode);
		
		if (is_array($result)) {
			$items = array();
			
			for ($i = 0, $len = count($result); $i < $len; $i++) {
				if (isset($result[$i])) {
					$items[] = $result[$i];
					unset($result[$i]);
				}
			}
			
			if (!isset($result['title'])) {
				$result['title'] = 'GeoRSS';
			}
			foreach ($result as $key => $val) {
				$node = $dom->createElement($key, urlencode($val));
				$rssNode->appendChild($node);
			}
			
			
			foreach ($items as $item) {
				$itemNode = $dom->createElement('item');
				$rssNode->appendChild($itemNode);
				
				if (!isset($item['title'])) {
					$item['title'] = isset($item['link']) ? $item['link'] : 'Untitled';
				}
				foreach ($item as $key => $val) {
					if ($key == 'the_geom') {
						if (preg_match('/^(\w+)\((.*?)\)$/', $val, $m)) {
							// GeoRSS 要求的是“纬度 经度”格式
							$parts = explode(' ', $m[2]);
							$converted = array();
							foreach ($parts as $i => $part) {
								$converted[$i] = $parts[$i % 2 ? $i - 1 : $i + 1];
							}
							$node = $dom->createElement('georss:' . strtolower($m[1]), implode(' ', $converted));
							$itemNode->appendChild($node);
						}
					} else {
						$node = @$dom->createElement(strtolower($key), $val);
						$itemNode->appendChild($node);
					}
				}
			}
		} else {
			return false;
		}
		echo $dom->saveXML();
	}
	function response_rss($result) {
		header('Content-Type: text/xml');
		$dom = new DOMDocument("1.0");
		
		$rssNode = $dom->createElement('rss');
		$dom->appendChild($rssNode);
		
		$channelNode = $dom->createElement('channel');
		$rssNode->appendChild($channelNode);
		
		if (is_array($result)) {
			$metas = array();
			$items = array();
			
			for ($i = 0, $len = count($result); $i < $len; $i++) {
				if (isset($result[$i])) {
					$items[] = $result[$i];
				} else {
					$metas[] = $result[$i];
				}
			}
			
			foreach ($metas as $key => $val) {
				$node = $dom->createElement($key, $val);
				$channelNode->appendChild($node);
			}
			
			foreach ($items as $item) {
				$itemNode = $dom->createElement('item');
				$channelNode->appendChild($itemNode);
				
				foreach ($item as $key => $val) {
					$node = $dom->createElement($key, $val);
					$itemNode->appendChild($node);
				}
			}
		} else {
			return false;
		}
		echo $dom->saveXML();
	}
}