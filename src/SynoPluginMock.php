<?php

class SynoPluginMock
{
    function addResult($title, $download, $size, $datetime, $page, $hash, $seeds, $leechs, $category) {
        echo "Title : $title\n";
        echo "Url : $download\n";
        echo "Seeds : $seeds\n";
        echo "Leechs : $leechs\n";
        echo "Size : $size\n";
        echo "Page : $page\n";
        echo "Hash : $hash\n";
        echo "Category : $category\n";
    }
}
