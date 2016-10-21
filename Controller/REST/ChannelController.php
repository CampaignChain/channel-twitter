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

namespace CampaignChain\Channel\TwitterBundle\Controller\REST;

use CampaignChain\Channel\TwitterBundle\REST\TwitterClient;
use CampaignChain\CoreBundle\Controller\REST\BaseModuleController;
use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\EntityService\LocationService;
use FOS\RestBundle\Controller\Annotations as REST;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Request\ParamFetcher;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * @REST\NamePrefix("campaignchain_channel_twitter_rest_")
 *
 * Class ChannelController
 * @package CampaignChain\Channel\TwitterBundle\Controller\REST
 */
class ChannelController extends BaseModuleController
{
    /**
     * Search for users on Twitter.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/p/campaignchain/channel-twitter/users/search?q=ordnas&location=42
     *
     * Example Response
     * ================
     *
    [
        {
            "insert_name":"ordnas",
            "display_name":"Sandro Groganz",
            "search_key":"ordnas Sandro Groganz",
            "image":"http:\/\/pbs.twimg.com\/profile_images\/669150051386683392\/VYPoxcqJ_normal.jpg"
        },
        {
            "insert_name":"Ordnas69",
            "display_name":"Sandro Summa",
            "search_key":"Ordnas69 Sandro Summa",
            "image":"http:\/\/pbs.twimg.com\
            /profile_images\/688442716074676225\/z5A7CmQs_normal.jpg"
        },
        {
            "insert_name":"sssaannddrrooo",
            "display_name":"Ordnas",
            "search_key":"sssaannddrrooo Ordnas",
            "image":"http:\/\/pbs.twimg.com\/profile_images\/667602635013361664
            \/s0qPns5T_normal.jpg"
        },
        {
            "insert_name":"OrdnasPB",
            "display_name":"Sandro",
            "search_key":"OrdnasPB Sandro",
            "image":"http:\/\/pbs.twimg.com\/profile_images\/711797319553961984\/MvwFTuwt_normal.jpg"
        },
        {
            "insert_name":"sandalagoa",
            "display_name":"Ordnas Aiam",
            "search_key":"sandalagoa Ordnas Aiam",
            "image":"http:\/\/pbs
            .twimg.com\/profile_images\/1550555986\/308999_10150323188574382_614399381_7994968_909345970_n_normal.jpg"
        },
        {
            "insert_name":"ordnas24",
            "display_name":"Sandro Bezerra",
            "search_key":"ordnas24 Sandro Bezerra",
            "image":"http:\/\/pbs.twimg.com\/profile_images\/544808863116304384\/IxcjtzZk_normal.jpeg"
        }
    ]
     *
     * @ApiDoc(
     *  section="Packages: Twitter"
     * )
     *
     * @REST\QueryParam(
     *      name="q",
     *      map=false,
     *      requirements="[A-Za-z0-9][A-Za-z0-9_.-]*",
     *      description="The search query to run against people search."
     *  )
     *
     * @REST\QueryParam(
     *      name="location",
     *      map=false,
     *      requirements="\d+",
     *      description="The ID of a CampaignChain Location you'd like to use to connect with Twitter."
     *  )
     */
    public function getUsersSearchAction(ParamFetcher $paramFetcher)
    {
        try {
            $params = $paramFetcher->all();

            /** @var LocationService $locationService */
            $locationService = $this->get('campaignchain.core.location');
            $location = $locationService->getLocation($params['location']);

            /** @var TwitterClient $channelRESTService */
            $channelRESTService = $this->get('campaignchain.channel.twitter.rest.client');
            $connection = $channelRESTService->connectByLocation($location);

            $request = $connection->get('users/search.json?q='.$params['q'].'&count=6&include_entities=false');
            $response = $request->send()->json();

            foreach($response as $user){
                $data[] = array(
                    'insert_name' => $user['screen_name'],
                    'display_name' => $user['name'],
                    'search_key' => $user['screen_name'].' '.$user['name'],
                    'image' => $user['profile_image_url'],
                );
            }

            return $this->response($data);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }
}