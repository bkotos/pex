<?php

namespace Pex;

class Test
{
    public function read(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('File not found');
        }

        $file = fopen($filePath, 'r');

        $isFirstIteration = true;
        while (($values = fgetcsv($file)) !== false) {
            if ($isFirstIteration) {
                $isFirstIteration = false;
            }
        }
        for ($i = 0; ($values = fgetcsv($file)) !== false; $i++) {}
    }
}
