<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
use Illuminate\Database\Capsule\Manager as Capsule;

class PleskMultiServer_Manager_V1630 extends PleskMultiServer_Manager_V1000
{
    protected function _getResellerPlans()
    {
        $result = PleskMultiServer_Registry::getInstance()->api->resellerPlan_get();
        $resellerPlans = array();
        foreach ($result->xpath('//reseller-plan/get/result') as $result) {
            $resellerPlans[] = new ResellerPlan((integer)$result->id, (string)$result->name);
        }
        return $resellerPlans;
    }

    protected function _getAccountInfo($params, $panelExternalId = null)
    {
        $accountInfo = array();
        if (is_null($panelExternalId)) {

            $this->createTableForAccountStorage();
            /** @var stdClass $account */
            $account = Capsule::table('mod_pleskmsaccounts')
                ->where('userid', $params['clientsdetails']['userid'])
                ->where('usertype', $params['type'])
                ->first();

            $panelExternalId = is_null($account) ? '' : $account->panelexternalid;
        }

        if ('' != $panelExternalId) {

            $requestParams = array( 'externalId' => $panelExternalId );
            switch ($params['type']) {

                case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                    try {
                        $result = PleskMultiServer_Registry::getInstance()->api->customer_get_by_external_id($requestParams);
                        if (isset($result->customer->get->result->id)) {
                            $accountInfo['id'] = (int)$result->customer->get->result->id;
                        }
                        if (isset($result->customer->get->result->data->gen_info->login)) {
                            $accountInfo['login'] = (string)$result->customer->get->result->data->gen_info->login;
                        }
                    } catch (Exception $e) {
                        if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                            throw $e;
                        }
                        throw new Exception(
                            PleskMultiServer_Registry::getInstance()->translator->translate(
                                'ERROR_CUSTOMER_WITH_EXTERNAL_ID_NOT_FOUND_IN_PANEL',
                                array(
                                    'EXTERNAL_ID' => $panelExternalId,
                                )
                            )
                            , PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND
                        );
                    }
                    break;

                case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                    try {
                        $result = PleskMultiServer_Registry::getInstance()->api->reseller_get_by_external_id($requestParams);
                        if (isset($result->reseller->get->result->id)) {
                            $accountInfo['id'] = (int)$result->reseller->get->result->id;
                        }
                        if (isset($result->reseller->get->result->data->{'gen-info'}->login)) {
                            $accountInfo['login'] = (string)$result->reseller->get->result->data->{'gen-info'}->login;
                        }
                    } catch (Exception $e) {
                        if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                            throw $e;
                        }
                        throw new Exception(
                            PleskMultiServer_Registry::getInstance()->translator->translate(
                                'ERROR_RESELLER_WITH_EXTERNAL_ID_NOT_FOUND_IN_PANEL',
                                array(
                                    'EXTERNAL_ID' => $panelExternalId,
                                )
                            )
                            , PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND
                        );
                    }
                    break;
            }

            return $accountInfo;
        }

        /** @var stdClass $hosting */
        $hosting = Capsule::table('tblhosting')
            ->where('server', $params['serverid'])
            ->where('userid', $params['clientsdetails']['userid'])
            ->first();

        $login = is_null($hosting) ? '' : $hosting->username;
        $requestParams = array('login' => $login);

        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:

                try {
                    $result = PleskMultiServer_Registry::getInstance()->api->customer_get_by_login($requestParams);
                    if (isset($result->customer->get->result->id)) {
                        $accountInfo['id'] = (int)$result->customer->get->result->id;
                    }
                    if (isset($result->customer->get->result->data->gen_info->login)) {
                        $accountInfo['login'] = (string)$result->customer->get->result->data->gen_info->login;
                    }
                } catch (Exception $e) {
                    if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                        throw $e;
                    }
                }
                break;

            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                try {
                    $result = PleskMultiServer_Registry::getInstance()->api->reseller_get_by_login($requestParams);
                    if (isset($result->reseller->get->result->id)) {
                        $accountInfo['id'] = (int)$result->reseller->get->result->id;
                    }
                    if (isset($result->reseller->get->result->data->{'gen-info'}->login)) {
                        $accountInfo['login'] = (string)$result->reseller->get->result->data->{'gen-info'}->login;
                    }
                } catch (Exception $e) {
                    if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                        throw $e;
                    }
                }
                break;
        }

        if (empty($accountInfo)) {
            throw new Exception(
                PleskMultiServer_Registry::getInstance()->translator->translate(
                    'ERROR_CUSTOMER_WITH_EMAIL_NOT_FOUND_IN_PANEL',
                    array('EMAIL' => $params['clientsdetails']['email'])
                ),
                PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND
            );
        }

        return $accountInfo;
    }

    /**
     * @param array $params
     * @return array
     */
    protected function _getAddAccountParams($params)
    {
        $result = parent::_getAddAccountParams($params);
        $result['externalId'] = $this->_getCustomerExternalId($params);
        return $result;
    }

    protected function _addAccount($params)
    {
        $accountId = null;
        $requestParams = $this->_getAddAccountParams($params);
        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                $result = PleskMultiServer_Registry::getInstance()->api->customer_add($requestParams);
                $accountId = (int)$result->customer->add->result->id;
                break;
            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                $requestParams = array_merge(
                    $requestParams,
                    array( 'planName' => PleskMultiServer_Object_ResellerPlan::getResellerPlanName($params))
                );
                $result = PleskMultiServer_Registry::getInstance()->api->reseller_add($requestParams);
                $accountId = (int)$result->reseller->add->result->id;
                break;
        }

        return $accountId;
    }

    protected function _addWebspace($params)
    {
        $requestParams = array(
            'domain' => $params['domain'],
            'ownerId' => $params['ownerId'],
            'username' => $params['username'],
            'password' => $params['password'],
            'status' => PleskMultiServer_Object_Webspace::STATUS_ACTIVE,
            'htype' => PleskMultiServer_Object_Webspace::TYPE_VRT_HST,
            'planName' => $params['configoption1'],
            'ipv4Address' => $params['ipv4Address'],
            'ipv6Address' => $params['ipv6Address'],
        );
        PleskMultiServer_Registry::getInstance()->api->webspace_add($requestParams);
    }

    protected function _setResellerStatus($params)
    {
        $accountInfo = $this->_getAccountInfo($params);
        if (!isset($accountInfo['id'])) {
            return;
        }
        PleskMultiServer_Registry::getInstance()->api->reseller_set_status(
            array(
                'status' => $params['status'],
                'id' => $accountInfo['id'],
            )
        );
    }

    protected function _deleteReseller($params)
    {
        $accountInfo = $this->_getAccountInfo($params);
        if (!isset($accountInfo['id'])) {
            return;
        }
        PleskMultiServer_Registry::getInstance()->api->reseller_del(array('id' => $accountInfo['id']));
    }

    protected function _setAccountPassword($params)
    {
        $accountInfo = $this->_getAccountInfo($params);
        if (!isset($accountInfo['id'])) {
            return;
        }

        if (isset($accountInfo['login']) && $accountInfo['login'] != $params["username"]) {
            return;
        }
        $requestParams = array(
            'id' => $accountInfo['id'],
            'accountPassword' => $params['password'],
        );

        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                PleskMultiServer_Registry::getInstance()->api->customer_set_password($requestParams);
                break;
            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                PleskMultiServer_Registry::getInstance()->api->reseller_set_password($requestParams);
                break;
        }
    }

    protected function _deleteWebspace($params)
    {
        PleskMultiServer_Registry::getInstance()->api->webspace_del( array('domain' => $params['domain']) );
        $accountInfo = $this->_getAccountInfo($params);
        if (!isset($accountInfo['id'])) {
            return;
        }
        $webspaces = $this->_getWebspacesByOwnerId($accountInfo['id']);
        if (!isset($webspaces->id)) {
            PleskMultiServer_Registry::getInstance()->api->customer_del( array('id' => $accountInfo['id']) );
        }
    }

    protected function _switchSubscription($params)
    {
        switch ($params['type']) {
            case PleskMultiServer_Object_Customer::TYPE_CLIENT:
                $result = PleskMultiServer_Registry::getInstance()->api->service_plan_get_by_name(array('name' => $params['configoption1']));
                $servicePlanResult = reset($result->xpath('//service-plan/get/result'));
                PleskMultiServer_Registry::getInstance()->api->switch_subscription(
                    array(
                        'domain' => $params['domain'],
                        'planGuid' => (string)$servicePlanResult->guid,
                    )
                );
                break;
            case PleskMultiServer_Object_Customer::TYPE_RESELLER:
                $result = PleskMultiServer_Registry::getInstance()->api->reseller_plan_get_by_name(
                    array('name' => PleskMultiServer_Object_ResellerPlan::getResellerPlanName($params))
                );
                $resellerPlanResult = reset($result->xpath('//reseller-plan/get/result'));
                $accountInfo = $this->_getAccountInfo($params);
                if (!isset($accountInfo['id'])) {
                    return;
                }
                PleskMultiServer_Registry::getInstance()->api->switch_reseller_plan(
                    array(
                        'id' => $accountInfo['id'],
                        'planGuid' => (string)$resellerPlanResult->guid,
                    )
                );
                break;
        }
    }


    protected function _processAddons($params)
    {
        $result = PleskMultiServer_Registry::getInstance()->api->webspace_subscriptions_get_by_name(array('domain' => $params['domain']));
        $planGuids = array();
        foreach($result->xpath('//webspace/get/result/data/subscriptions/subscription/plan/plan-guid') as $guid) {
            $planGuids[] = (string)$guid;
        }
        $webspaceId = (int)$result->webspace->get->result->id;
        $exludedPlanGuids = array();

        $servicePlan = PleskMultiServer_Registry::getInstance()->api->service_plan_get_by_guid(array('planGuids' => $planGuids));
        foreach($servicePlan->xpath('//service-plan/get/result') as $result) {
            try {
                $this->_checkErrors($result);
                $exludedPlanGuids[] = (string)$result->guid;
            } catch (Exception $e) {
                if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }

        $addons = array();
        $addonGuids = array_diff($planGuids, $exludedPlanGuids);
        if (!empty($addonGuids)) {
            $addon = PleskMultiServer_Registry::getInstance()->api->service_plan_addon_get_by_guid(array('addonGuids' => $addonGuids));
            foreach($addon->xpath('//service-plan-addon/get/result') as $result) {
                try {
                    $this->_checkErrors($result);
                    $addons[(string)$result->guid] = (string)$result->name;
                } catch (Exception $e) {
                    if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                        throw $e;
                    }
                }
            }
        }

        $addonsToRemove = array();
        $addonsFromRequest = array();
        foreach($params['configoptions'] as $addonTitle => $value) {
            // if value = 1 then add-on has question (yes/no) type and should be process a little another way. if 0 - we should not include this add-on to processing.
            if ("0" == $value) {
                continue;
            }
            if (0 !== strpos($addonTitle, PleskMultiServer_Object_Addon::ADDON_PREFIX)) {
                continue;
            }

            $pleskAddonTitle = substr_replace($addonTitle, '', 0, strlen(PleskMultiServer_Object_Addon::ADDON_PREFIX));

            $addonsFromRequest[] = ("1" == $value) ? $pleskAddonTitle : $value;
        }
        foreach($addons as $guid => $addonName) {
            if (!in_array($addonName, $addonsFromRequest))  {
                $addonsToRemove[$guid] = $addonName;
            }
        }

        $addonsToAdd = array_diff($addonsFromRequest, array_values($addons));
        foreach($addonsToRemove as $guid => $addon) {
            PleskMultiServer_Registry::getInstance()->api->webspace_remove_subscription(
                array(
                    'planGuid' => $guid,
                    'id' => $webspaceId,
                )
            );
        }
        foreach($addonsToAdd as $addonName) {
            $addon = PleskMultiServer_Registry::getInstance()->api->service_plan_addon_get_by_name(array('name' => $addonName));
            foreach($addon->xpath('//service-plan-addon/get/result/guid') as $guid) {
                PleskMultiServer_Registry::getInstance()->api->webspace_add_subscription(
                    array(
                        'planGuid' => (string)$guid,
                        'id' => $webspaceId,
                    )
                );
            }

        }
    }

    /**
     * @param $params
     * @return array (<domainName> => array ('diskusage' => value, 'disklimit' => value, 'bwusage' => value, 'bwlimit' => value))
     * @throws Exception
     */
    protected function _getWebspacesUsage($params)
    {
        $usage = array();
        $data = PleskMultiServer_Registry::getInstance()->api->webspace_usage_get_by_name(array('domains' => $params['domains']));
        foreach($data->xpath('//webspace/get/result') as $result) {
            try {
                $this->_checkErrors($result);
                $domainName = (string)$result->data->gen_info->name;
                $usage[$domainName]['diskusage'] = (float)$result->data->gen_info->real_size;
                $usage[$domainName]['bwusage'] = (float)$result->data->stat->traffic;
                $usage[$domainName] = array_merge($usage[$domainName], $this->_getLimits($result->data->limits));
            } catch (Exception $e) {
                if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        // Calculate traffic for additional domains
        foreach($data->xpath('//site/get/result') as $result) {
            try {
                $parentDomainName = (string)reset($result->xpath('filter-id'));
                $usage[$parentDomainName]['bwusage'] += (float)$result->data->stat->traffic;
            } catch (Exception $e) {
                if (PleskMultiServer_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }

        //Data saved in megabytes, not in a bytes
        foreach($usage as $domainName => $domainUsage) {
            foreach($domainUsage as $param => $value) {
                $usage[$domainName][$param] = $usage[$domainName][$param] / (1024 * 1024);
            }
        }
        return $usage;
    }

    protected function _addIpToIpPool($accountId, $params) {}

    protected function _getWebspacesByOwnerId($ownerId)
    {
        $result = PleskMultiServer_Registry::getInstance()->api->webspaces_get_by_owner_id( array('ownerId' => $ownerId) );
        return $result->webspace->get->result;
    }

    protected function _getCustomerExternalId($params)
    {
        return PleskMultiServer_Object_Customer::getCustomerExternalId($params);
    }

    protected function _changeSubscriptionIp($params)
    {
        $webspace = PleskMultiServer_Registry::getInstance()->api->webspace_get_by_name(array('domain' => $params['domain']));
        $ipDedicatedList = $this->_getIpList(PleskMultiServer_Object_Ip::DEDICATED);
        $oldIp[PleskMultiServer_Object_Ip::IPV4] = (string)$webspace->webspace->get->result->data->hosting->vrt_hst->ip_address;

        $ipv4Address = isset($oldIp[PleskMultiServer_Object_Ip::IPV4]) ? $oldIp[PleskMultiServer_Object_Ip::IPV4] : '';
        if (
            PleskMultiServer_Object_Ip::getIpOption($params) == 'IPv4 none; IPv6 shared'
            || PleskMultiServer_Object_Ip::getIpOption($params) == 'IPv4 none; IPv6 dedicated'
        ) {
            $ipv4Address = '';
        }

        if (!empty($params['ipv4Address'])) {
            if (isset($oldIp[PleskMultiServer_Object_Ip::IPV4]) && ($oldIp[PleskMultiServer_Object_Ip::IPV4] != $params['ipv4Address']) &&
                (!in_array($oldIp[PleskMultiServer_Object_Ip::IPV4], $ipDedicatedList) || !in_array($params['ipv4Address'], $ipDedicatedList))) {
                $ipv4Address = $params['ipv4Address'];
            } elseif (!isset($oldIp[PleskMultiServer_Object_Ip::IPV4])) {
                $ipv4Address = $params['ipv4Address'];
            }
        }

        if (!empty($ipv4Address)) {
            PleskMultiServer_Registry::getInstance()->api->webspace_set_ip(
                array(
                    'domain' => $params['domain'],
                    'ipv4Address' => $ipv4Address,
                )
            );
        }
    }

    protected function _getLimits(SimpleXMLElement $limits)
    {
        $result = array();
        foreach($limits->limit as $limit) {
            $name = (string)$limit->name;
            switch ($name) {
                case 'disk_space':
                    $result['disklimit'] = (float)$limit->value;
                    break;
                case 'max_traffic':
                    $result['bwlimit'] = (float)$limit->value;
                    break;
                default:
                    break;
            }
        }
        return $result;
    }
}
