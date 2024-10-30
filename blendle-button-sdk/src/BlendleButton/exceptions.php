<?php

namespace BlendleButton;

class Exception extends \Exception
{
}
class InvalidArgumentException extends Exception
{
}
class MissingCustomerIDException extends InvalidArgumentException
{
}
class MissingCustomerSecretException extends InvalidArgumentException
{
}
class MissingPublicKeyException extends InvalidArgumentException
{
}
class InvalidCredentialsException extends InvalidArgumentException
{
}
