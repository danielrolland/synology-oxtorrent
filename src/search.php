<?php
class SynoDLMSearchOxTorrent {

	private $domain = 'https://www.oxtorrent.vc';
	private $qurl = '/recherche/';
	public $max_results = 0;
	public $verbose = false;

	public function prepare($curl, $query) {
		$url = $this->domain . $this->qurl . urlencode($query);
		curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	}

	/**
	 * Returns a size in bytes
	 *
	 * @param size 		unmodified size (e.g. 1)
	 * @param modifier	modifier (i.e. 'KB', 'MB', 'GB', 'TB')
	 * @return bytesize	size in bytes (e.g. 1,048,576)
	 */
	private function sizeInBytes($size, $modifier) {
		switch (strtoupper($modifier)) {
		case 'KB':
			return $size * 1024;
		case 'MB':
			return $size * 1024 * 1024;
		case 'GB':
			return $size * 1024 * 1024 * 1024;
		case 'TB':
			return $size * 1024 * 1024 * 1024 * 1024;
		default:
			return $size;
		}
	}

	public function parse($plugin, $response) {

        $regx = '/<td.*>.*<a href="[^"]*" title/';

		if (!($result_count = preg_match_all($regx, $response, $rows, PREG_SET_ORDER))) {
			return 0;
		}

        $list = [];

        foreach ($rows as $row) {

            preg_match('/<a href=\".+\"/', $row[0], $output );
            $list[] = $this->domain . substr($output[0], 9, -1);
        }

        $torrents = [];
        $index = 0;

        foreach ($list as $entry) {

            var_dump($entry);
            echo "<br>";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $entry);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            if(curl_error($ch)) {
                var_dump(curl_error($ch));
            }
            curl_close($ch);

            // TITRE
            $title_regex = "/<title>.+<\/title>/";
            $length = strlen('<title>');
            preg_match($title_regex, $output, $filtered);
            $title = substr($filtered[0], $length, -$length-1);
            $torrents[$index]["title"] = $title;

            // URL
            try {
                $url = $this->getRelativeLinkUrl($output);
                $torrents[$index]["url"] = $this->domain . $url;
            } catch (Exception $e) {
                echo "Link for $title is probably a click bait, skipping\n";
                $torrents[$index]["title"] = "INVALID - " . $title;
                $torrents[$index]["url"] = "INVALID - " . $this->domain . $url;
            }



            // HASH
            $torrents[$index]["hash"] = $this->getHash($output);

            // DETAILS
            preg_match('/<div class=\"block-detail\"><i class=\"fa fa-th-large\"> <\/i>.+<\/div>\n<table class=\"table\">.+<\/table>/s', $output, $details);
            preg_match_all('/<td .+<\/td>/', $details[0], $res);
            $torrents[$index]["size"] = $this->getDetail($res[0][2]);
            $torrents[$index]["seeds"] = $this->getDetail($res[0][3], true);
            $torrents[$index]["leechers"] = $this->getDetail($res[0][4], true);
            $torrents[$index]["category"] = strip_tags($this->getDetail($res[0][0]));

            $size_array = explode(" ", $torrents[$index]["size"]);
            $plugin->addResult(
                $torrents[$index]["title"],
                $torrents[$index]["url"],
                $this->sizeInBytes($size_array[0], $size_array[1]),
                date("Y-m-d H:i:s", time()),
                $entry,
                $torrents[$index]["hash"],
                intval($torrents[$index]["seeds"]),
                intval($torrents[$index]["leechers"]),
                $torrents[$index]["category"]
            );
            $index++;

        }

		return count($torrents);

	}

    /**
     * @throws Exception
     */
    private function getRelativeLinkUrl($curlResponse) {
        $prefix = "window.location.href = '";
        $suffix = "'";
        $url_regex = "/$prefix.+$suffix/";
        $length = strlen($prefix);
        $result = preg_match($url_regex, $curlResponse, $filtered);
        $url = "";
        if ($result === 0) {
            echo "Couldn't find link in javascript, looking for regular href";
            $prefix = "<div class='download'><div class='btn-download'><a href='";
            $suffix = "><img";
            $url_regex = "/$prefix.+$suffix/";
            $length = strlen($prefix);
            echo strlen($suffix);
            $result = preg_match($url_regex, $curlResponse, $filtered);
            if ($result !== 0) {
                $url = substr($filtered[0], $length, - strlen($suffix));
                $checkJavascript = preg_match("/javascript:/", $filtered[0]);
                if ($checkJavascript !== 0) {
                    throw new Exception("Found javascript tag in the link but no corresponding code, probably a click bait - skip");
                }
            }
        } else {
            $url = substr($filtered[0], $length, -1);
        }
        return $url;
    }

	private function getHash($input) {
        $prefix = "window.location.href = 'magnet:?xt=urn:btih:";
        $regex_prefix = str_replace("?", "\?", $prefix);
        $suffix = "';";
	    $result = $this->extractString($input, $prefix, $suffix);
	    if ($result === 0) {
	        echo "Hash is not in Javascript function, looking inside HTML\n";
	        $prefix = "<div class='btn-magnet'><a href='magnet:?xt=urn:btih:"; // ? character is escaped, substract 1 from strlen
            $suffix = "&";
            $result = $this->extractString($input, $prefix, $suffix);
            if ($result === 0) {
                $result = "Can't find hash";
            }
        }
	    echo $result;
	    return strip_tags($result);
    }

	private function getDetail($input, $numericalOnly = false) {
        $details_regex = '/<strong>.+<\/strong>/';
        preg_match($details_regex , $input, $output);
        $size = substr($output[0], strlen('<strong>'), - strlen('</strong>'));
        if ($numericalOnly) {
            preg_match('/[0-9]+/', $output[0], $numericalOutput);
            $size = $numericalOutput[0];
        }
	    return $size;
    }


    private function extractString($rawString, $prefix, $suffix) {
        $regex_prefix = str_replace("?", "\?", $prefix);
        $hash_regex = "/$regex_prefix.+$suffix/";
        $result = preg_match($hash_regex, $rawString,$rawHash);
        if ($result === 0) {
            return 0;
        }
        return substr($rawHash[0], strlen($prefix), - strlen($suffix));
    }
}
?>
