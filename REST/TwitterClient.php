<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\TwitterBundle\REST;

use Symfony\Component\HttpFoundation\Session\Session;
use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;

class TwitterClient
{
    const RESOURCE_OWNER = 'Twitter';
    const BASE_URL   = 'https://api.twitter.com';

    protected $container;

    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function connectByActivity($activity){
        $oauthApp = $this->container->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        // Get Access Token and Token Secret
        $oauthToken = $this->container->get('campaignchain.security.authentication.client.oauth.token');
        $token = $oauthToken->getToken($activity->getLocation());

        return $this->connect($application->getKey(), $application->getSecret(), $token->getAccessToken(), $token->getTokenSecret());
    }

    public function connect($appKey, $appSecret, $accessToken, $tokenSecret){
        try {
            $client = new Client(self::BASE_URL.'/{version}', array('version' => '1.1'));
            $oauth  = new OauthPlugin(array(
                'consumer_key'    => $appKey,
                'consumer_secret' => $appSecret,
                'token'           => $accessToken,
                'token_secret'    => $tokenSecret,
            ));

            return $client->addSubscriber($oauth);
        }
        catch (ClientErrorResponseException $e) {

            $req = $e->getRequest();
            $resp =$e->getResponse();
            print_r($resp);die('1');
        }
        catch (ServerErrorResponseException $e) {

            $req = $e->getRequest();
            $resp =$e->getResponse();
            die('2');
        }
        catch (BadResponseException $e) {
            $req = $e->getRequest();
            $resp =$e->getResponse();
            print_r($resp);
            die('3');
        }
        catch( Exception $e){
            echo "AGH!";
            die('4');
        }
    }
}