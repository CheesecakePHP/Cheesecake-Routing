<?php


namespace Cheesecake\Validator;

/**
 * Validator to validate if a value is a number or not.
 *
 * @package Cheesecake\Validator
 * @author Christian Meyer <ak56Lk@gmx.net>
 * @version 1.0
 * @since 0.1
 */
class Number implements ValidatorInterface
{

    /**
     * Validates the given value if it is a number or not.
     * If it is a number then it returns TRUE otherwise it
     * returns FALSE.
     *
     * @param $value
     * @return bool
     */
    public static function validate($value)
    {
        $return = false;

        if(is_numeric($value)) {
            $return = true;
        }

        return $return;
    }

}