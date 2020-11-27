<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterAlyanteAdaptor\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_URL = 'transporter_alyante/general/webservice_url';
    const TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_USERNAME = 'transporter_alyante/general/webservice_username';
    const TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_PASSWORD = 'transporter_alyante/general/webservice_password';
    const TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_AUTHORIZATION_SCOPE = 'transporter_alyante/general/webservice_authorization_scope';
    const TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_RESPONSE_COMPRESSED = 'transporter_alyante/general/webservice_response_compressed';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     */
    public function getWebserviceUrl(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_URL,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return string
     */
    public function getWebserviceUsername(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_USERNAME,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return string
     */
    public function getWebservicePassword(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_PASSWORD,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return string
     */
    public function getAuthorizationScope(): string
    {
        return (string)$this->scopeConfig->getValue(
            self::TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_AUTHORIZATION_SCOPE,
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * @return bool
     */
    public function isResponseCompressed(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::TRANSPORTER_ALYANTE_GENERAL_WEBSERVICE_RESPONSE_COMPRESSED,
            ScopeInterface::SCOPE_WEBSITE
        );
    }
}
