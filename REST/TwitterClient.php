<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace CampaignChain\Channel\TwitterBundle\REST;

use CampaignChain\CoreBundle\Entity\Location;
use Exception;
use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Exception\ServerErrorResponseException;
use Guzzle\Plugin\Oauth\OauthPlugin;

class TwitterClient
{
    const RESOURCE_OWNER = 'Twitter';
    const BASE_URL = 'https://api.twitter.com';

    protected $container;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function connectByActivity($activity)
    {
        $oauthApp = $this->container->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        // Get Access Token and Token Secret
        $oauthToken = $this->container->get('campaignchain.security.authentication.client.oauth.token');
        $token = $oauthToken->getToken($activity->getLocation());

        return $this->connect(
            $application->getKey(),
            $application->getSecret(),
            $token->getAccessToken(),
            $token->getTokenSecret()
        );
    }

    public function connect($appKey, $appSecret, $accessToken, $tokenSecret)
    {
        try {
            $client = new Client(self::BASE_URL.'/{version}', ['version' => '1.1']);

            $oauth = new OauthPlugin(
                [
                    'consumer_key' => $appKey,
                    'consumer_secret' => $appSecret,
                    'token' => $accessToken,
                    'token_secret' => $tokenSecret,
                ]
            );

            return $client->addSubscriber($oauth);
        } catch (ClientErrorResponseException $e) {
            $req = $e->getRequest();
            $resp = $e->getResponse();
            print_r($resp);
            die('1');
        } catch (ServerErrorResponseException $e) {
            $req = $e->getRequest();
            $resp = $e->getResponse();
            die('2');
        } catch (BadResponseException $e) {
            $req = $e->getRequest();
            $resp = $e->getResponse();
            print_r($resp);
            die('3');
        } catch (Exception $e) {
            echo 'AGH!';
            die('4');
        }
    }

    public function connectByLocation(Location $location)
    {
        $oauthApp = $this->container->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        // Get Access Token and Token Secret
        $oauthToken = $this->container->get('campaignchain.security.authentication.client.oauth.token');
        $token = $oauthToken->getToken($location);

        return $this->connect(
            $application->getKey(),
            $application->getSecret(),
            $token->getAccessToken(),
            $token->getTokenSecret()
        );
    }
}
