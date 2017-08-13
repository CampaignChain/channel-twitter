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

use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\CoreBundle\Exception\ExternalApiException;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\ApplicationService;
use CampaignChain\Security\Authentication\Client\OAuthBundle\EntityService\TokenService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;

class TwitterClient
{
    const RESOURCE_OWNER = 'Twitter';
    const BASE_URL = 'https://api.twitter.com';

    protected $oauthAppService;
    protected $oauthTokenService;
    /** @var  Client */
    protected $client;

    public function __construct(
        ApplicationService $oauthAppService,
        TokenService $oautTokenService
    )
    {
        $this->oauthAppService = $oauthAppService;
        $this->oauthTokenService = $oautTokenService;
    }

    public function connectByActivity(Activity $activity)
    {
        $application = $this->oauthAppService->getApplication(self::RESOURCE_OWNER);

        // Get Access Token and Token Secret
        $token = $this->oauthTokenService->getToken($activity->getLocation());

        return $this->connect(
            $application->getKey(),
            $application->getSecret(),
            $token->getAccessToken(),
            $token->getTokenSecret()
        );
    }

    public function connectByLocation(Location $location)
    {
        $application = $this->oauthAppService->getApplication(self::RESOURCE_OWNER);

        // Get Access Token and Token Secret
        $token = $this->oauthTokenService->getToken($location);

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
            $stack = HandlerStack::create();

            $oauth = new Oauth1(
                [
                    'consumer_key' => $appKey,
                    'consumer_secret' => $appSecret,
                    'token' => $accessToken,
                    'token_secret' => $tokenSecret,
                ]
            );

            $stack->push($oauth);

            $this->client = new Client([
                'base_uri' => self::BASE_URL.'/1.1/',
                'handler' => $stack,
                'auth' => 'oauth'
            ]);

            return $this;
        } catch (Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode());
        }
    }

    private function request($method, $uri, $body = array())
    {
        try {
            $res = $this->client->request($method, $uri, $body);
            return json_decode($res->getBody(), true);
        } catch(Exception $e){
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }

    public function uploadImage($path)
    {
        return $this->request('POST','https://upload.twitter.com/1.1/media/upload.json',
            [
                'multipart' => [
                    [
                        'name' => 'media_data',
                        'contents' => base64_encode(file_get_contents($path)),
                    ]
                ]
            ]
        );
    }

    public function uploadImages(array $paths)
    {
        $mediaIds = array();

        foreach($paths as $path){
            $res = $this->uploadImage($path);
            $mediaIds[] = $res['media_id'];
        }

        return $mediaIds;
    }

    public function postStatus($text, array $imgPaths = array())
    {
        $data['status'] = $text;

        if(count($imgPaths)){
            $mediaIds = array();

            $mediaIds = $this->uploadImages($imgPaths);

            if (count($mediaIds)) {
                $data['media_ids'] = implode(',', $mediaIds);
            }
        }

        return $this->request('POST', 'statuses/update.json', [
            'query' => $data,
        ]);
    }

    public function getOembed($id)
    {
        return $this->request('GET', 'statuses/oembed.json?id='.$id);
    }

    public function getUserNames($name, $count = 6)
    {
        return $this->request('GET', 'users/search.json?q='.$name.'&count='.$count.'&include_entities=false');
    }

    public function getSameUserTweets($name, $text, \DateTime $since)
    {
        return $this->request('GET',
            'search/tweets.json?q='
            . urlencode(
                'from:' . $name . ' '
                . '"' . $text . '" '
                . 'since:' . $since->format('Y-m-d')
            )
        );
    }

    public function getUser($id)
    {
        return $this->request('GET', 'users/show.json?user_id='.$id);
    }

    public function getTweetStats($id)
    {
        $res = $this->request('GET', 'statuses/retweets/'.$id.'.json?count=1&trim_user=true');

        // If response is an empty array, this means no interaction happened yet
        // with the Tweet.
        if(count($res)){
            $stats['retweet_count'] = $res[0]['retweeted_status']['retweet_count'];
            $stats['favorite_count'] = $res[0]['retweeted_status']['favorite_count'];
        } else {
            $stats['retweet_count'] = 0;
            $stats['favorite_count'] = 0;
        }

        return $stats;
    }
}
