<?php
/*
 * Copyright © Websolute spa. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Websolute\TransporterAlyanteAdaptor\Downloader;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Webapi\Response;
use Magento\Framework\Webapi\Rest\Request;
use Monolog\Logger;
use Websolute\TransporterActivity\Api\ActivityRepositoryInterface;
use Websolute\TransporterAlyanteAdaptor\Api\AlyanteParamsInterface;
use Websolute\TransporterAlyanteAdaptor\Model\Config;
use Websolute\TransporterAmqp\Api\DownloaderWaitMeBeforeContinueInterface;
use Websolute\TransporterBase\Api\TransporterConfigInterface;
use Websolute\TransporterBase\Exception\TransporterException;
use Websolute\TransporterEntity\Api\Data\EntityInterface;
use Websolute\TransporterEntity\Model\EntityModelFactory;
use Websolute\TransporterEntity\Model\EntityRepository;

class AlyanteWebJsonDownloader implements DownloaderWaitMeBeforeContinueInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var TransporterConfigInterface
     */
    private $config;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var array
     */
    private $params;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EntityModelFactory
     */
    private $entityModelFactory;

    /**
     * @var EntityRepository
     */
    private $entityRepository;

    /**
     * @var ActivityRepositoryInterface
     */
    private $activityRepository;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var Config
     */
    private $alyanteConfig;

    /**
     * @param Logger $logger
     * @param TransporterConfigInterface $config
     * @param string $identifier
     * @param AlyanteParamsInterface $params
     * @param Config $alyanteConfig
     * @param SerializerInterface $serializer
     * @param EntityModelFactory $entityModelFactory
     * @param EntityRepository $entityRepository
     * @param ActivityRepositoryInterface $activityRepository
     * @param ClientInterface $client
     */
    public function __construct(
        Logger $logger,
        TransporterConfigInterface $config,
        string $identifier,
        AlyanteParamsInterface $params,
        Config $alyanteConfig,
        SerializerInterface $serializer,
        EntityModelFactory $entityModelFactory,
        EntityRepository $entityRepository,
        ActivityRepositoryInterface $activityRepository,
        ClientInterface $client
    ) {
        $this->alyanteConfig = $alyanteConfig;
        $this->logger = $logger;
        $this->config = $config;
        $this->identifier = $identifier;
        $this->params = $params;
        $this->serializer = $serializer;
        $this->entityModelFactory = $entityModelFactory;
        $this->entityRepository = $entityRepository;
        $this->activityRepository = $activityRepository;
        $this->client = $client;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @throws TransporterException
     * @throws NoSuchEntityException
     */
    public function execute(int $activityId, string $downloaderType): void
    {
        $this->activityRepository->getById($activityId);

        $webserviceUrl = $this->getWebserviceUrl($activityId, $downloaderType);
        $webserviceUsername = $this->getWebserviceUsername($activityId, $downloaderType);
        $webservicePassword = $this->getWebservicePassword($activityId, $downloaderType);
        $resourceName = $this->getResourceName($activityId, $downloaderType);
        $method = $this->getMethod($activityId, $downloaderType);
        $urlParams = $this->getUrlParams($activityId, $downloaderType);

        $webserviceUrl .= $resourceName . $urlParams;

        $this->client->setCredentials($webserviceUsername, $webservicePassword);
        $this->client->addHeader('Authorization-Scope', $this->alyanteConfig->getAuthorizationScope());
        $this->client->addHeader('Accept', 'application/json');
        $this->client->addHeader('Content-Type', 'application/json');

        if ($this->alyanteConfig->isResponseCompressed()) {
            $this->client->addHeader('Accept-Encoding', 'gzip, compress');
        }

        switch ($method) {
            case Request::HTTP_METHOD_GET:
                $this->client->get($webserviceUrl);
                break;
            case Request::HTTP_METHOD_POST:
                $this->client->post($webserviceUrl, $this->params->getBody());
                break;
        }
        $body = $this->client->getBody();

        if ($this->client->getStatus() !== Response::HTTP_OK) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ httpBody:%3',
                $activityId,
                $downloaderType,
                $body
            ));
        }

        try {
            if ($this->alyanteConfig->isResponseCompressed()) {
                $body = gzdecode($body);
            }
            $response = $this->serializer->unserialize($body);
            $rows = $response['data'];
        } catch (Exception $e) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                $e->getMessage()
            ));
        }

        $ok = 0;
        $ko = 0;

        $identifierIndex = explode('.', $this->identifier);

        foreach ($rows as $row) {
            try {
                $dataOriginal = $this->serializer->serialize($row);

                $identifier = $row;

                foreach ($identifierIndex as $i) {
                    $identifier = $identifier[$i];
                }

                /** @var EntityInterface $entity */
                $entity = $this->entityModelFactory->create();
                $entity->setActivityId($activityId);
                $entity->setType($downloaderType);
                $entity->setIdentifier($identifier);
                $entity->setDataOriginal($dataOriginal);

                $this->entityRepository->save($entity);
                $ok++;
            } catch (Exception $e) {
                if ($this->config->continueInCaseOfErrors()) {
                    $this->logger->error(__(
                        'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ KO ~ error:%3',
                        $activityId,
                        $downloaderType,
                        $e->getMessage()
                    ));
                    $ko++;
                } else {
                    throw new TransporterException(__(
                        'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ KO ~ error:%3',
                        $activityId,
                        $downloaderType,
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->logger->info(__(
            'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ okCount:%3 koCount:%4',
            $activityId,
            $downloaderType,
            $ok,
            $ko
        ));
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getWebserviceUrl(int $activityId, string $downloaderType): string
    {
        $webserviceUrl = $this->alyanteConfig->getWebserviceUrl();

        if (!$webserviceUrl) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing webservice_url'
            ));
        }

        return $webserviceUrl;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getWebserviceUsername(int $activityId, string $downloaderType): string
    {
        $webserviceUsername = $this->alyanteConfig->getWebserviceUsername();

        if (!$webserviceUsername) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing webservice_username'
            ));
        }

        return $webserviceUsername;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getWebservicePassword(int $activityId, string $downloaderType): string
    {
        $webservicePassword = $this->alyanteConfig->getWebservicePassword();

        if (!$webservicePassword) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing webservice_password'
            ));
        }

        return $webservicePassword;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getResourceName(int $activityId, string $downloaderType): string
    {
        $resourceName = $this->params->getResourceName();

        if (!$resourceName) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing resource name'
            ));
        }

        return $resourceName;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getMethod(int $activityId, string $downloaderType): string
    {
        $method = $this->params->getMethod();

        if (!$method) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing method'
            ));
        }

        if (!in_array($method, [Request::HTTP_METHOD_GET, Request::HTTP_METHOD_POST])) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Invalid method'
            ));
        }

        return $method;
    }

    /**
     * @param int $activityId
     * @param string $downloaderType
     * @return string
     * @throws TransporterException
     */
    public function getUrlParams(int $activityId, string $downloaderType): string
    {
        $urlParams = $this->params->getUrlParams();

        if (!$urlParams) {
            throw new TransporterException(__(
                'activityId:%1 ~ Downloader ~ downloaderType:%2 ~ ERROR ~ error:%3',
                $activityId,
                $downloaderType,
                'Missing method'
            ));
        }

        return $urlParams;
    }
}
