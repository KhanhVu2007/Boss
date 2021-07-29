<?php

declare(strict_types=1);

namespace Labality\Boss;

class ChanceLibrary
{

    //stackoverflow pasted hahah
    public static function getRandomWeightedElement(array $weightedValues) {
        $rand = mt_rand(1, (int) array_sum($weightedValues));

        foreach ($weightedValues as $key => $value) {
            $rand -= $value;
            if ($rand <= 0) {
                return $key;
            }
        }
    }
}