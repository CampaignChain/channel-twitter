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

namespace CampaignChain\Channel\TwitterBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\Location\TwitterBundle\Entity\TwitterUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class TwitterController extends Controller
{
    const RESOURCE_OWNER = 'Twitter';

    private $applicationInfo = [
        'key_labels' => ['key', 'App Key'],
        'secret_labels' => ['secret', 'App Secret'],
        'config_url' => 'https://apps.twitter.com',
        'parameters' => [
            'force_login' => true,
        ],
    ];

    public function createAction()
    {
        $oauthApp = $this->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        if (!$application) {
            return $oauthApp->newApplicationTpl(self::RESOURCE_OWNER, $this->applicationInfo);
        } else {
            return $this->render(
                'CampaignChainChannelTwitterBundle:Create:index.html.twig',
                [
                    'page_title' => 'Connect with Twitter',
                    'app_id' => $application->getKey(),
                ]
            );
        }
    }

    public function loginAction()
    {
        $oauth = $this->get('campaignchain.security.authentication.client.oauth.authentication');
        $status = $oauth->authenticate(self::RESOURCE_OWNER, $this->applicationInfo);
        $profile = $oauth->getProfile();

        if ($status) {
            try {
                $em = $this->getDoctrine()->getManager();
                $em->getConnection()->beginTransaction();

                $wizard = $this->get('campaignchain.core.channel.wizard');
                $wizard->setName($profile->displayName);

                // Get the location module.
                $locationService = $this->get('campaignchain.core.location');
                $locationModule = $locationService->getLocationModule(
                    'campaignchain/location-twitter',
                    'campaignchain-twitter-user'
                );

                $location = new Location();
                $location->setIdentifier($profile->identifier);
                $location->setName($profile->displayName);
                $location->setLocationModule($locationModule);
                $location->setImage($profile->photoURL);
                $location->setUrl($profile->profileURL);

                $wizard->addLocation($location->getIdentifier(), $location);

                $channel = $wizard->persist();
                $wizard->end();

                $oauth->setLocation($channel->getLocations()[0]);

                $twitterUser = new TwitterUser();
                $twitterUser->setLocation($channel->getLocations()[0]);
                $twitterUser->setIdentifier($profile->identifier);
                $twitterUser->setDisplayName($profile->firstName);
                $twitterUser->setUsername($profile->displayName);
                $twitterUser->setProfileImageUrl($profile->photoURL);
                $twitterUser->setProfileUrl($profile->profileURL);

                $em->persist($twitterUser);

                // schedule job to get metrics from now on
                if ($channel->getLocations()[0]->getLocationModule()->getIdentifier(
                    ) === 'campaignchain-twitter-user'
                ) {
                    $this->get('campaignchain.job.report.location.twitter')->schedule($channel->getLocations()[0]);
                }

                $em->flush();

                $em->getConnection()->commit();

                $this->addFlash(
                    'success',
                    'The Twitter location <a href="#">'.$profile->displayName.'</a> was connected successfully.'
                );
            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                throw $e;
            }
        } else {
            // A channel already exists that has been connected with this Facebook account
            $this->addFlash(
                'warning',
                'A location has already been connected for this Twitter account.'
            );
        }

        return $this->render(
            'CampaignChainChannelTwitterBundle:Create:login.html.twig',
            [
                'redirect' => $this->generateUrl('campaignchain_core_location'),
            ]
        );
    }
}
