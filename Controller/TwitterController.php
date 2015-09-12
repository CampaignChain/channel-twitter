<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\TwitterBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\Location\TwitterBundle\Entity\TwitterUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;

class TwitterController extends Controller
{
    const RESOURCE_OWNER = 'Twitter';

    private $applicationInfo = array(
        'key_labels' => array('key', 'App Key'),
        'secret_labels' => array('secret', 'App Secret'),
        'config_url' => 'https://apps.twitter.com',
        'parameters' => array(
            "force_login" => true,
        ),
    );

    public function createAction()
    {
        $oauthApp = $this->get('campaignchain.security.authentication.client.oauth.application');
        $application = $oauthApp->getApplication(self::RESOURCE_OWNER);

        if(!$application){
            return $oauthApp->newApplicationTpl(self::RESOURCE_OWNER, $this->applicationInfo);
        }
        else {
            return $this->render(
                'CampaignChainChannelTwitterBundle:Create:index.html.twig',
                array(
                    'page_title' => 'Connect with Twitter',
                    'app_id' => $application->getKey(),
                )
            );
        }
    }

    public function loginAction(Request $request){
            $oauth = $this->get('campaignchain.security.authentication.client.oauth.authentication');
            $status = $oauth->authenticate(self::RESOURCE_OWNER, $this->applicationInfo);
            $profile = $oauth->getProfile();

            if($status){
                try {
                    $repository = $this->getDoctrine()->getManager();
                    $repository->getConnection()->beginTransaction();

                    $wizard = $this->get('campaignchain.core.channel.wizard');
                    $wizard->setName($profile->displayName);

                    // Get the location module.
                    $locationService = $this->get('campaignchain.core.location');
                    $locationModule = $locationService->getLocationModule('campaignchain/location-twitter', 'campaignchain-twitter-user');

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

                    $repository->persist($twitterUser);
                    $repository->flush();

                    $repository->getConnection()->commit();

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        'The Twitter location <a href="#">'.$profile->displayName.'</a> was connected successfully.'
                    );
                } catch (\Exception $e) {
                    $repository->getConnection()->rollback();
                    throw $e;
                }
            } else {
                // A channel already exists that has been connected with this Facebook account
                $this->get('session')->getFlashBag()->add(
                    'warning',
                    'A location has already been connected for this Twitter account.'
                );
            }

        return $this->render(
            'CampaignChainChannelTwitterBundle:Create:login.html.twig',
            array(
                'redirect' => $this->generateUrl('campaignchain_core_channel')
            )
        );
    }
}