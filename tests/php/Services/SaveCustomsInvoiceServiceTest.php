<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR  . 'TestTimeException.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Helpers' . DIRECTORY_SEPARATOR . 'MockPluginApp.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Overrides' . DIRECTORY_SEPARATOR . 'ReplacePluginApp.php');

use InvalidArgumentException;
use Plenty\Modules\Document\Contracts\DocumentRepositoryContract;
use Plenty\Modules\Document\Models\Document;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\General\DocumentDTO;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\Test\TestTimeException;

/**
 * Tests for InventoryUpdateService
 */
final class SaveCustomsInvoiceServiceTest extends \PHPUnit\Framework\TestCase
{
    const RESULT_INSTRUCTION_PASS = 'p';
    const RESULT_INSTRUCTION_FAIL = 'f';
    const RESULT_INSTRUCTION_EXCEPTION = 'e';

    const PO_NUM = 'somePO';
    const ORDER_NUM = 123;
    const DOC_URL = 'http://wayfair-api/customsInvoice/somePO';

    const UPLOAD_RESULT_GOOD = ['good', 'upload'];

    const DOC_DATA = 'b64DocData';
    const DOC_TYPE = Document::UPLOADED;

    const DOC_NUMBER = '12345';

    const DOC_PAYLOAD =  [
        'documents' => [
            [
                'content' => self::DOC_DATA,
                'numberWithPrefix' => 'customs' . self::DOC_NUMBER,
                'number' => self::DOC_NUMBER
            ]
        ]
    ];

    /**
     * @before
     */
    public function setUp()
    {
        // set up the pluginApp, which returns empty mocks by default
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);
    }

    /**
     * Test harness for Save
     *
     * @param string $name
     * @param array $expectedResult
     * @param string|null $expectedException
     * @param integer $plentyOrderId
     * @param string $wfPoNumber
     * @param string $documentURL
     * @param string $fetchResultType
     * @param string $uploadResultType
     *
     * @return void
     *
     * @dataProvider dataProviderForSave
     */
    public function testSave(
        string $name,
        array $expectedResult,
        $expectedException = null,
        int $plentyOrderId,
        string $wfPoNumber,
        string $documentURL,
        string $fetchResultType = self::RESULT_INSTRUCTION_PASS,
        string $uploadResultType = self::RESULT_INSTRUCTION_PASS
    ) {
        /** @var FetchDocumentContract&\PHPUnit\Framework\MockObject\MockObject */
        $fetchDocumentContract = $this->createMock(FetchDocumentContract::class);

        /** @var DocumentRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $documentRepositoryContract = $this->createMock(DocumentRepositoryContract::class);

        $fetchInvocation = null;
        $uploadInvocation = null;

        if ($expectedException != InvalidArgumentException::class) {
            $fetchInvocation = $fetchDocumentContract->expects($this->once())->method('fetch')->with($documentURL);
            if ($fetchResultType == self::RESULT_INSTRUCTION_PASS) {
                /** @var DocumentDTO&\PHPUnit\Framework\MockObject\MockObject */
                $documentDto = $this->createMock(DocumentDTO::class);
                $documentDto->method('getBase64EncodedContent')->willReturn(self::DOC_DATA);
                $fetchInvocation->willReturn($documentDto);
                $uploadInvocation = $documentRepositoryContract->expects($this->once())->method('uploadOrderDocuments')->with($plentyOrderId, self::DOC_TYPE, self::DOC_PAYLOAD);
                if ($uploadResultType == self::RESULT_INSTRUCTION_PASS) {
                    $uploadInvocation->willReturn(self::UPLOAD_RESULT_GOOD);
                } elseif ($uploadResultType == self::RESULT_INSTRUCTION_FAIL) {
                    $uploadInvocation->willReturn([]);
                } else {
                    $uploadInvocation->willThrowException(new TestTimeException("Forced upload failure"));
                }
            } else {
                if ($fetchResultType == self::RESULT_INSTRUCTION_FAIL) {
                    $fetchInvocation->willReturn(null);
                } else {
                    $fetchInvocation->willThrowException(new TestTimeException("Forced fetch failure"));
                }
            }
        }

        if (!isset($fetchInvocation)) {
            $fetchDocumentContract->expects($this->never())->method('fetch');
        }

        if (!isset($uploadInvocation)) {
            $documentRepositoryContract->expects(($this->never()))->method('uploadOrderDocuments');
        }

        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $loggerContract = $this->createMock(LoggerContract::class);

        /** @var LogSenderService&\PHPUnit\Framework\MockObject\MockObject */
        $logSenderService = $this->createMock(LogSenderService::class);

        /** @var SaveCustomsInvoiceService&\PHPUnit\Framework\MockObject\MockObject */
        $saveCustomsInvoiceService = $this->createPartialMock(SaveCustomsInvoiceService::class, ['generateDocNumber']);
        $saveCustomsInvoiceService->__construct($documentRepositoryContract, $fetchDocumentContract, $loggerContract, $logSenderService);
        $saveCustomsInvoiceService->method('generateDocNumber')->willReturn(self::DOC_NUMBER);

        $actualResult = [];

        if (isset($expectedException) && !empty(trim($expectedException))) {
            $this->expectException($expectedException);
        }

        $actualResult = $saveCustomsInvoiceService->save($plentyOrderId, $wfPoNumber, $documentURL);

        $this->assertEquals($expectedResult, $actualResult, $name);
    }

    /**
     * Test cases for testSave
     *
     * @return array
     */
    public function dataProviderForSave(): array
    {
        $cases = [];

        $cases[] = ['negative order ID should cause InvalidArgumentException', [], InvalidArgumentException::class, -3, self::PO_NUM, self::DOC_URL];

        $cases[] = ['zero order ID should cause InvalidArgumentException', [], InvalidArgumentException::class, 0, self::PO_NUM, self::DOC_URL];

        $cases[] = ['empty PO number should cause InvalidArgumentException', [], InvalidArgumentException::class, self::ORDER_NUM, '', self::DOC_URL];
        $cases[] = ['whitespace PO number should cause InvalidArgumentException', [], InvalidArgumentException::class, self::ORDER_NUM, '      ', self::DOC_URL];

        $cases[] = ['empty URL should cause InvalidArgumentException', [], InvalidArgumentException::class, self::ORDER_NUM, self::PO_NUM, ''];
        $cases[] = ['whitespace URL should cause InvalidArgumentException', [], InvalidArgumentException::class, self::ORDER_NUM, self::PO_NUM, '     '];

        $cases[] = ['failure at fetch time should result in lack of upload', [], null, self::ORDER_NUM, self::PO_NUM, self::DOC_URL, self::RESULT_INSTRUCTION_FAIL];
        $cases[] = ['exception at fetch time should result in lack of upload', [], null, self::ORDER_NUM, self::PO_NUM, self::DOC_URL, self::RESULT_INSTRUCTION_EXCEPTION];

        // Expectations are in place to make sure we aren't attempting to upload after a failed fetch, so there is no need for those cases.

        $cases[] = ['failure at upload time should have empty result', [], null, self::ORDER_NUM, self::PO_NUM, self::DOC_URL, self::RESULT_INSTRUCTION_PASS, self::RESULT_INSTRUCTION_FAIL];
        $cases[] = ['exception at upload time should have empty result', [], null, self::ORDER_NUM, self::PO_NUM, self::DOC_URL, self::RESULT_INSTRUCTION_PASS, self::RESULT_INSTRUCTION_EXCEPTION];

        $cases[] = ['perfect inputs and good responses from other modules should lead to an upload', self::UPLOAD_RESULT_GOOD, null, self::ORDER_NUM, self::PO_NUM, self::DOC_URL];

        // TODO: add more cases

        return $cases;
    }
}
