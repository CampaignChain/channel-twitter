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
    const CONTROLLER_SERVICE = 'campaignchain.activity.controller.twitter.update_status';

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
            "twitter_status": {
                "id": 26,
                "message": "Alias quaerat natus iste libero. Et dolor assumenda odio sequi. http://www.schmeler.biz/nostrum-quia-eaque-quo-accusantium-voluptatem.html",
                "createdDate": "2015-12-14T11:02:23+0000"
            }
        },
        {
            "status_location": {
                "id": 63,
                "status": "unpublished",
                "createdDate": "2015-12-14T11:02:23+0000"
            }
        },
        {
            "activity": {
                "id": 82,
                "equalsOperation": true,
                "name": "Announcement 26 on Twitter",
                "startDate": "2012-01-10T05:23:34+0000",
                "status": "paused",
                "createdDate": "2015-12-14T11:02:23+0000"
            }
        },
        {
            "operation": {
                "id": 58,
                "name": "Announcement 26 on Twitter",
                "startDate": "2012-01-10T05:23:34+0000",
                "status": "open",
                "createdDate": "2015-12-14T11:02:23+0000"
            }
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

            /** @var TwitterClient $twitterRESTService */
            $twitterRESTService = $this->get('campaignchain.channel.twitter.rest.client');
            $connection = $twitterRESTService->connectByLocation($location);

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