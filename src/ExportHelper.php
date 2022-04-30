<?php


namespace Alive2212\LaravelSmartRestful;


class ExportHelper
{
    /**
     * @param string $fileName
     * @param $data
     * @param string $path
     */
    public function createCsv(string $fileName, $data, string $path = '/public/export')
    {
        $insertHeader = false;
        $counter = 0;
        $myFile = fopen(storage_path() . "/app" . $path . "/" . $fileName .".csv", "a+");
        foreach ($data as $user) {
            if ($insertHeader == false) {
                $insertHeader = true;
                fwrite($myFile, implode(";", array_merge(["#"], array_keys($user)))."\n");
            }
            fwrite($myFile, implode(";", array_merge([++$counter], $user))."\n");
        }
        fclose($myFile);
    }
}
