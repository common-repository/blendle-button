<?php

namespace BlendleButton;

require 'exceptions.php';

/**
 * Client is the Blendle Button API client object.
 *
 * It holds secret keys and options and allows you to generate and validate
 * tokens.
 */
class Client
{
    private $customer_id;
    private $customer_secret;
    private $public_key;
    private $options;

    const API_SECRET_PATTERN = "/[0-9a-f]{4}(?:[0-9a-f]{4}-){4}[0-9a-f]{12}/";

    /**
     * Construct this client for a Blendle customer/publisher, checks if all
     * keys are present.
     *
     * @param string  $customer_id     Your ID as supplied by Blendle.
     * @param string  $customer_secret Your secret key, paired with your ID.
     * @param string  $public_key      Blendle's public key, used to validate acquisitions.
     * @param mixed[] $options
     */
    public function __construct($customer_id, $customer_secret, $public_key, $options = array())
    {
        $options = array_merge(array('production' => true), $options);

        if (empty($customer_id)) {
            throw new MissingCustomerIDException('missing customer_id');
        }

        if (empty($customer_secret)) {
            throw new MissingCustomerSecretException('missing customer_secret');
        }

        if (empty($public_key)) {
            throw new MissingPublicKeyException('missing public_key');
        }

        if (!preg_match(self::API_SECRET_PATTERN, $customer_secret)) {
            throw new InvalidCredentialsException('invalid customer_secret');
        }

        if (strpos($public_key, '\n') !== false) {
            $public_key = str_replace($public_key, '\n', "\n");
        }

        $this->customer_id = $customer_id;
        $this->customer_secret = trim($customer_secret);
        $this->public_key = $public_key;
        $this->options = $options;
    }

    /**
     * isItemAcquired will check if the given token is a valid acquired token, when
     * this is true it'll compare the item_id with the given value and return true
     * when the tiven token is valid for this item_id. Otherwise, return false.
     *
     * This token is normally supplied by the Client as the X-PWB-Token header.
     *
     * @param string $item_id The item_id to check for.
     * @param string $token   The acquisition to validate.
     *
     * @return bool Whether or not the token is valid for this item_id.
     */
    public function isItemAcquired($item_id, $token)
    {
        try {
            $decoded = \JWT::decode($token, $this->public_key, array('RS256'));

            if ($decoded->data && $decoded->data->acquired) {
                $claimed_item_id = isset($decoded->data->foreign_uid) ? $decoded->data->foreign_uid : $decoded->data->item_uid;

                return $claimed_item_id == $item_id;
            }
        } catch (\UnexpectedValueException $e) {
        } catch (\DomainException $e) {
        }

        return false;
    }

    /**
     * isActiveSubscription checks if the given token data contains a valid and
     * authorized subscription for the publisher of this Client.
     *
     * This token is normally supplied by the Client as the X-PWB-Token header.
     *
     * @param string The subscription token.
     *
     * @return bool Whether or not the token is valid.
     */
    public function isActiveSubscription($token)
    {
        try {
            $decoded = \JWT::decode($token, $this->public_key, array('RS256'));

            return $decoded->data && $decoded->data->subscription && $decoded->aud == $this->customer_id &&
               $decoded->sub === 'subscription' && $decoded->data->provider_uid == $this->customer_id;
        } catch (\UnexpectedValueException $e) {
        } catch (\DomainException $e) {
        }

        return false;
    }

    /**
     * testCredentials is a debug function that does one http call to the
     * Blendle backend to check if your credentials are setup correctly.
     *
     * @return bool Returns true if everything checks out.
     */
    public function testCredentials()
    {
        list($token, $nonce) = $this->checkCredentialsChallenge();

        $headers = array(
            'Content-Type: application/jwt',
            'Accept: application/jwt'
        );

        $http = array('http' => array(
            'method' => 'POST',
            'header' => join("\r\n", $headers),
            'content' => $token,
            'ignore_errors' => true
        ));

        $context = stream_context_create($http);
        $response = file_get_contents($this->checkCredentialsURL(), false, $context);

        if (isset($http_response_header)) {
            preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $response_headers);

            if (end($response_headers) != 200) {
                throw new InvalidCredentialsException('could not validate credentials');
            }
        }

        return $this->validateCredentials($response, $nonce);
    }

    /**
     * generateItemToken will create a signed request token about an item to sell.
     *
     * The resulting token can be passed to the Button client which it will send
     * to the Blendle backend.
     *
     * @param string  $item_id   The item_id that this token wil describe.
     * @param mixed[] $meta_data An associative array containing meta data about
     *                           the item: title, description, words, etc..
     */
    public function generateItemToken($item_id, $meta_data = array())
    {
        $meta_data['foreign_uid'] = $item_id;

        return \JWT::encode(array(
            'data' => $meta_data,
            'iss' => $this->customer_id,
            'sub' => 'item',
            'iat' => time(),
        ), $this->customer_secret);
    }

    /**
     * clientURL returns the javascript client url to be used, this is based on
     * set environment.
     */
    public function clientURL()
    {
        $client_url = isset($_ENV['BUTTON_CLIENTJS_URL']) ? $_ENV['BUTTON_CLIENTJS_URL'] : getenv('PAY_CLIENTJS_URL');

        if (empty($client_url)) {
            $client_url = $this->options['production'] ? 'https://pay.blendle.com/client/js/client.js' : 'https://pay.blendle.io/client/js/client.js';
        }

        return $client_url;
    }

    /**
     * checkCredentialsURL returns the endpoint to test the credentials.
     */
    private function checkCredentialsURL()
    {
        $host = $this->options['production'] ? 'pay.blendle.com' : 'pay.blendle.io';
        $check_credentials_url = sprintf('https://%s/api/provider/%s/check_credentials', $host, $this->customer_id);

        return $check_credentials_url;
    }

    /**
     * checkCredentialsChallenge returns the JWT and nonce that will
     * be used to check the credentials.
     */
    private function checkCredentialsChallenge()
    {
        $nonce = $this->generateNonce();
        $token = \JWT::encode(array(
            'data' => array('nonce' => $nonce),
            'iss' => $this->customer_id,
            'sub' => 'check_credentials',
            'exp' => time() + 900,
        ), $this->customer_secret);

        return array($token, $nonce);
    }

    /**
     * generateNonce returns a random 32 character string on default.
     */
    public function generateNonce($length = 32)
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, $length);
    }

    /**
     * validateCredentials will validate the credentials with the Blendle backend.
     *
     * @param string $token The credentials to validate.
     * @param string $nonce Random generated string.
     *
     * @return bool Whether or not the token is valid.
     */
    private function validateCredentials($token, $nonce)
    {
        try {
            $decoded = \JWT::decode($token, $this->public_key, array('RS256'));

            return $decoded->data && $decoded->data->nonce &&
               $decoded->sub === 'check_credentials' && $decoded->data->nonce == $nonce;
        } catch (\UnexpectedValueException $e) {
            throw new InvalidCredentialsException('invalid customer_secret');
        } catch (\DomainException $e) {
            throw new InvalidCredentialsException('invalid customer_secret');
        }

        return false;
    }
}
