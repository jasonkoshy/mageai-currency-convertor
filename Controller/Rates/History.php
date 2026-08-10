<?php
declare(strict_types=1);

namespace MageAI\CurrencyConverter\Controller\Rates;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use MageAI\CurrencyConverter\Model\FrankfurterClient;
use Psr\Log\LoggerInterface;

class History implements HttpGetActionInterface
{
    /**
     * Allowed range shorthands mapped to a number of days back from today.
     */
    private const RANGES = [
        '1W' => 7,
        '1M' => 30,
        '3M' => 90,
        '6M' => 180,
        '1Y' => 365,
    ];

    /**
     * 
     * @param RequestInterface  $request
     * @param JsonFactory       $resultJsonFactory
     * @param FrankfurterClient $frankfurterClient
     * @param DateTime          $dateTime
     * @param LoggerInterface   $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FrankfurterClient $frankfurterClient,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Load rate history 
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        $base = (string)$this->request->getParam('base', '');
        $quote = (string)$this->request->getParam('quote', '');
        $range = strtoupper((string)$this->request->getParam('range', '1M'));

        if ($base === '' || $quote === '') {
            return $result->setHttpResponseCode(400)->setData(
                [
                'success' => false,
                'message' => (string)__('Both "base" and "quote" currency codes are required.'),
                ]
            );
        }

        if (!isset(self::RANGES[$range])) {
            $range = '1M';
        }

        $days = self::RANGES[$range];
        $to = date('Y-m-d', $this->dateTime->gmtTimestamp());
        $from = date('Y-m-d', $this->dateTime->gmtTimestamp() - ($days * 86400));

        try {
            $history = $this->frankfurterClient->getHistory($base, $quote, $from, $to);

            return $result->setData(
                [
                'success' => true,
                'range' => $range,
                'from' => $from,
                'to' => $to,
                'series' => $history,
                ]
            );
        } catch (LocalizedException $e) {
            $this->logger->warning('MageAI_CurrencyConverter: ' . $e->getMessage());

            return $result->setHttpResponseCode(502)->setData(
                [
                'success' => false,
                'message' => $e->getMessage(),
                ]
            );
        } catch (\Throwable $e) {
            $this->logger->error('MageAI_CurrencyConverter: ' . $e->getMessage());

            return $result->setHttpResponseCode(500)->setData(
                [
                'success' => false,
                'message' => (string)__('Unable to load rate history right now. Please try again later.'),
                ]
            );
        }
    }
}
