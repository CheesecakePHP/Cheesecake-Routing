<?php


namespace Cheesecake\Routing\Exception;


use Throwable;

class ControllerNotExistsException extends \Cheesecake\Http\Exception\Error_404
{

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
