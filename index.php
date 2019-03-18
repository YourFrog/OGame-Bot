<?php

namespace OGame {

	class Resource {
		public $metal;
		
		public $crystal;
		
		public $deuter;
	}
	
	class Build {
		public $name;
		
		public $level;
		
		public $updateLink;
	}
	
	class PageParser 
	{
		static public function getResource(string $html)
		{
			$converter = new \Symfony\Component\CssSelector\CssSelectorConverter();

			$doc = new \DOMDocument();
			@$doc->loadHTML($html);

			$xpath = new \DOMXpath($doc);

			$ogameResource = new Resource();
			$ogameResource->metal = (int) trim($xpath->query($converter->toXPath('span#resources_metal'))[0]->textContent);
			$ogameResource->crystal = (int) trim($xpath->query($converter->toXPath('span#resources_crystal'))[0]->textContent); 
			$ogameResource->deuter = (int) trim($xpath->query($converter->toXPath('span#resources_deuterium'))[0]->textContent);
			
			return $ogameResource;
		}
		
		static public function getMines(string $html)
		{
			$converter = new \Symfony\Component\CssSelector\CssSelectorConverter();

			$doc = new \DOMDocument();
			@$doc->loadHTML($html);

			$xpath = new \DOMXpath($doc);
			
			
			$nodeList = $xpath->query($converter->toXPath('ul#building div.buildingimg'));
			
			$result = [];
			
			foreach($nodeList as $node) {
			
				$build = new Build();
				$nodesUpgrade = $xpath->query($converter->toXPath('a.fastBuild'), $node);
				
				if( $nodesUpgrade->length == 1 ) {
					$link = $nodesUpgrade[0]->getAttribute('onclick');
					$link = substr($link, strpos($link, 'http'));
					$link = substr($link, 0, strpos($link, "'"));
					
					$build->updateLink = $link;
				}
								
				$nodeInfo = $xpath->query($converter->toXPath('a#details span.level'), $node)[0];
				
				$str = $nodeInfo->textContent;
				$str = trim($str);
				
				$split = explode(' ', $str);
				$split = array_map(function($value) { return trim($value); }, $split);
				$split = array_filter($split, function($value) {
					return $value !== '';
				});
				
				$build->name = implode(' ', array_slice($split, 0, count($split) - 1));
				$build->level = (int) end($split);
				
				$result[$build->name] = $build;
			}
			
			return $result;
		}
	}
}

namespace {
	require 'vendor/autoload.php';

	use GuzzleHttp\Client;

	$queue = [
		'Elektrownia słoneczna',
		'Kopalnia metalu',
		'Kopalnia metalu',
		'Elektrownia słoneczna',
		'Kopalnia metalu',
		'Kopalnia metalu',
		'Elektrownia słoneczna',
		'Kopalnia metalu',
		'Elektrownia słoneczna',
		'Kopalnia kryształu',
		'Kopalnia kryształu',
		'Kopalnia kryształu',
		'Elektrownia słoneczna',
		'Kopalnia metalu',
		'Kopalnia kryształu',
		'Elektrownia słoneczna',
		'Ekstraktor deuteru',
		'Ekstraktor deuteru',
		'Ekstraktor deuteru',
		'Elektrownia słoneczna',
		'Ekstraktor deuteru',
		'Ekstraktor deuteru',
		'Elektrownia słoneczna',
		'Kopalnia kryształu',
		'Kopalnia kryształu',
	];

	function findNextFromQueue($queue, $mines)
	{
		for($i = 0; $i < count($queue) - 1; $i++) {
			$mineName = $queue[$i];
			
			if( array_key_exists($mineName, $mines) === false ) {
				
				var_dump($mines);
				die('Brak zdefiniowanego klucza');
			}
			
			$mine = $mines[$mineName];
			$mine->level -= 1;
			
			if( $mine->level <= 0 ) {
				return $mine;
			}
		}
		
		return null;
	}
	
	$client = new Client(['cookies' => true]);
	$response = $client->request('POST', 'https://lobby-api.ogame.gameforge.com/users', [
		'form_params' => [
			'credentials[email]' => 'stedi2@o2.pl',
			'credentials[password]' => 'Gitgit12',
			'autologin' => false,
			'language' => 'pl',
			'kid' => ''
		]
	]);

	if( $response->getStatusCode() !== 200 ) {
		throw new \Exception('Strona nie zwróciła 200');
	}


	$response = $client->request('GET', 'https://lobby-api.ogame.gameforge.com/users/me/loginLink?id=114854&server[language]=pl&server[number]=158');

	if( $response->getStatusCode() !== 200 ) {
		throw new \Exception('Strona nie zwróciła 200');
	}

	$content = json_decode((string) $response->getBody());

	if( !isset($content->url) ) {
		throw new \Exception('Nie udało się pobrać adresu URL do serwera');
	}

	$response = $client->request('GET', $content->url);
	$content = (string) $response->getBody();

	$ogameResource = OGame\PageParser::getResource($content);

	
	$response = $client->request('GET', 'https://s158-pl.ogame.gameforge.com/game/index.php?page=resources');
	$content = (string) $response->getBody();
	
	
	$mines = OGame\PageParser::getMines($content);

	var_dump($mines);	
	var_dump('Surowce: Metal: ' . $ogameResource->metal . ', Crystal: ' . $ogameResource->crystal . ', Deuter: ' . $ogameResource->deuter);
	
	$mine = findNextFromQueue($queue, $mines);
	
	if( $mine === null ) {
		throw new \Exception('Nic nie odnalazłem');
	}
	
	if( $mine->updateLink === null ) {
		throw new \Exception('Nie można budować: ' . $mine->name);
	}
	
	var_dump('Bede budowac: ' . $mine->name);
	$response = $client->request('GET', $mine->updateLink);
	
	// Szukamy elektrowni
	die('Koniec');
}


