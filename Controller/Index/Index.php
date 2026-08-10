<?php
declare(strict_types=1);

namespace MageAI\CurrencyConverter\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    /**
     * 
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        private readonly PageFactory $resultPageFactory
    ) {
    }

    /**
     * 
     * @return Page
     */
    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Currency Exchange Converter'));

        return $resultPage;
    }
}
