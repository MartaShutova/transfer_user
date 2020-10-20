<?php

namespace Bundles\CoreBundle\Command\sqs\user;

use Bundles\CoreBundle\Command\sqs\BaseConsumerCommand;
...
use Symfony\Component\Console\Input\InputOption;

class TransferStudentConsumerCommand extends BaseConsumerCommand
{
    public function configure()
    {
        $this->setName('sqs:transfer-student:consumer')
            ->setDescription('Transfer student between campuses')
            ->addArgument('queue', null, InputOption::VALUE_REQUIRED, 'transfer-student');
    }

    public function processMessage($message)
    {

        $message = current($message);
        $data = @unserialize($message['Body']);
        if ($data) {

            $oldCompany = CompanyQuery::create()->findOneByDbName($data['oldDB']);
            $newCompany = CompanyQuery::create()->findOneByDbName($data['newDB']);
            $newClassKey = $newCompany->getTag();

            $value = unserialize($data['data']);

            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);

            $oldUser = UserQuery::create()->findOneById($value['Id']);
            $con = \Propel::getConnection();
            try {
                $con->beginTransaction();
                if(
                $this->getContainer()->get('user_transfer_service')->transfer(
                    $oldUser,
                    $oldCompany->getId(),
                    $newCompany->getId(),
                    UserTransfer::TYPE_CROSS_CAMPUS_APPLICATION)
                ) {
                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $oldUser
                        ->setInoperable(true)
                        ->save();

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                    $newUser = UserQuery::create()->findOneByGlobalUserId($value['GlobalUserId']);
                    $newUserId = $newUser->getId();
                    $newSno = $newUser->getSno();

                    $newUserAddress = AddressDataQuery::create()->findOneByUserId($newUserId);

                    $this->getContainer()->get('database_switcher')->changeDatabase('global');
                    $transferUsers = UserTransferQuery::create()
                        ->filterByFromSno($value['Sno'])
                        ->filterByToSno($newSno, \Criteria::NOT_LIKE)
                        ->_or()
                        ->filterByToSno($value['Sno'])
                        ->find();

                    if ($transferUsers) {
                        foreach ($transferUsers as $transferUser) {
                            if ($transferUser->getFromSno() == $value['Sno']) {
                                $transferUser->setFromSno($newSno);
                                $transferUser->setFromCompanyId($newCompany->getId());
                            } else {
                                $transferUser->setToSno($newSno);
                                $transferUser->setToCompanyId($newCompany->getId());
                            }
                            $transferUser->save();
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $us = UserStatusQuery::create()->findOneByUserId($value['Id']);
                    if ($us) {
                        $userStatus = $us->toArray();
                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                        if (!UserStatusQuery::create()->findOneByUserId($newUserId)) {
                            $status = new UserStatus();
                            $status
                                ->setUserId($newUserId)
                                ->setUkbaStatusId($userStatus['UkbaStatusId'])
                                ->save();
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $userSED = UserStudyEndDateQuery::create()->findOneByUserId($value['Id']);
                    if ($userSED) {
                        $userStudyEndDate = $userSED->toArray();
                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                        if (UserStudyEndDateQuery::create()->findOneByUserId($newUserId) == null) {
                            $used = new UserStudyEndDate();
                            $used->setUserId($newUserId)
                                ->setAdminId($userStudyEndDate['AdminId'])
                                ...
                                ->setReason($userStudyEndDate['Reason'])
                                ->save();
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $userVS = UserVisaStatusQuery::create()->findOneByUserId($value['Id']);
                    if ($userVS) {
                        $userVisaStatus = $userVS->toArray();
                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                        if (!UserVisaStatusQuery::create()->findOneByUserId($newUserId)) {
                            $uvs = new UserVisaStatus();
                            $uvs->setUserId($newUserId)
                                ...
                                ->save();
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $up = UserPendingQuery::create()->findOneByUserId($value['Id']);
                    if ($up) {
                        $userPending = $up->toArray();
                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                        if (!UserPendingQuery::create()->findOneByUserId($newUserId)) {
                            $up = new UserPending();
                            $up->setUserId($newUserId)
                                ->setCurrencyId($userPending['CurrencyId'])
                                ->setAmount($userPending['Amount'])
                                ->save();
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $uac = UserAgentCacheQuery::create()->findOneByUserId($value['Id']);
                    if ($uac) {
                        $userAgentCache = $uac->toArray();
                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                        if (UserAgentCacheQuery::create()->findOneByUserId($newUserId) == null) {
                            $uac = new UserAgentCache();
                            $uac->setUserId($newUserId)
                                ->setOldAgent($userAgentCache['OldAgent'])
                                ->setApplicationAgents($userAgentCache['ApplicationAgents'])
                                ->setApplicationSecondAgents($userAgentCache['ApplicationSecondAgents'])
                                ->save();
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    if ($userNotes = NoteQuery::create()->findByUserId($value['Id'])) {
                        foreach ($userNotes as $un) {
                            $userNote = $un->toArray();
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                            $noteType = NoteTypeQuery::create()->findOneById($userNote['NoteTypeId'])->toArray();
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                            if ($newNoteType = NoteTypeQuery::create()->findOneByTag($noteType['Tag'])) {
                                $newNote = new Note();
                                $newNote
                                    ->setUserId($newUserId)
                                    ...
                                    ->setGroup($userNote['Group'])
                                    ->save();
                            }
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $demands = DemandQuery::create()->findByUserId($value['Id']);
                    $demandsIds = [];
                    $vatReceiptIds = [];
                    if (count($demands) > 0) {
                        foreach ($demands as $dem) {
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                            $vatReceipts = VatReceiptQuery::create()->findByDemandId($dem->getId());
                            $demand = $dem->toArray();
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                            $d = new Demand();
                            $d->setUserId($newUserId)
                                ->setUpdatedAt($demand['UpdatedAt'])
                                ->setCreatedAt($demand['CreatedAt'])
                                ->setClassKey($newClassKey)
                                ->save();
                            $demandsIds[$demand['Id']] = $d->getId();
                            if (count($vatReceipts) > 0) {
                                foreach ($vatReceipts as $vr) {
                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                    $vatReceipt = $vr->toArray();
                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                    $newVatReceipt = new VatReceipt();
                                    $newVatReceipt
                                        ->setNumber($vatReceipt['Number'])
                                        ...
                                        ->setTuition($vatReceipt['Tuition'])
                                        ->save();
                                    $vatReceiptIds[$vatReceipt['Id']] = $newVatReceipt->getId();
                                }
                            }
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                    $applications = ApplicationQuery::create()->findByUserId($value['Id']);
                    if (count($applications) > 0) {
                        foreach ($applications as $a) {
                            $application = $a->toArray();
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                            if (!ApplicationQuery::create()->findOneByExternalId($application['Id'])) {

                                $app = new Application();
                                $app->setTitle($application['Title'])
                                    ...
                                    ->setNotForHesa($application['NotForHesa'])
                                    ->save();

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $appBoardStatuses = AppBoardStatusStackQuery::create()->findByApplicationId($application['Id']);
                                if (count($appBoardStatuses) > 0) {
                                    foreach ($appBoardStatuses as $abs) {
                                        $appBoardStatus = $abs->toArray();

                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                        $newABS = new AppBoardStatusStack();
                                        $newABS
                                            ->setUserId($newUserId)
                                            ...
                                            ->setAdminId($appBoardStatus['AdminId'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $appProductPapers = AppProductPaperQuery::create()->findByApplicationId($application['Id']);
                                if (count($appProductPapers) > 0) {
                                    foreach ($appProductPapers as $apps) {
                                        $appProductPaper = $apps->toArray();

                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                        $newAppProductPaper = new AppProductPaper();
                                        $newAppProductPaper
                                            ->setUserId($newUserId)
                                            ...
                                            ->setCreatedAt($appProductPaper['CreatedAt'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $appBoardConditions = AppBoardConditionStackQuery::create()->findByApplicationId($application['Id']);
                                if (count($appBoardConditions) > 0) {
                                    foreach ($appBoardConditions as $abc) {
                                        $appBoardCondition = $abc->toArray();

                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                        $newABC = new AppBoardConditionStack();
                                        $newABC
                                            ->setUserId($newUserId)
                                            ...
                                            ->setAdminId($appBoardCondition['AdminId'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $appStatusStacks = AppStatusStackQuery::create()->findByAppId($application['Id']);
                                if (count($appStatusStacks) > 0) {
                                    foreach ($appStatusStacks as $ass) {
                                        $appStatusStack = $ass->toArray();
                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);

                                        $newASS = new AppStatusStack();
                                        $newASS
                                            ->setAdminId($appStatusStack['AdminId'])
                                            ...
                                            ->setAppReasonForWithdrawalId($appStatusStack['AppReasonForWithdrawalId'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $appConditionStacks = AppConditionStackQuery::create()->findByApplicationId($application['Id']);
                                if (count($appConditionStacks) > 0) {
                                    foreach ($appConditionStacks as $appConditionStack) {
                                        $acs = $appConditionStack->toArray();
                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);

                                        $newACS = new AppConditionStack();
                                        $newACS
                                            ->setUserId($newUserId)
                                            ...
                                            ->setAdminId($acs['AdminId'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $appEnrolmentLetterStacks = AppEnrolmentLetterStackQuery::create()->findByApplicationId($application['Id']);
                                if (count($appEnrolmentLetterStacks) > 0) {
                                    foreach ($appEnrolmentLetterStacks as $appEnrolmentLetterStack) {
                                        $aels = $appEnrolmentLetterStack->toArray();
                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);

                                        $newAELS = new AppEnrolmentLetterStack();
                                        $newAELS
                                            ->setUserId($newUserId)
                                            ...
                                            ->setAdminId($aels['AdminId'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $partnerInstitutionDetails = PartnerInstitutionDetailsQuery::create()->findByApplicationId($application['Id']);
                                if (count($partnerInstitutionDetails) > 0) {
                                    foreach ($partnerInstitutionDetails as $partnerInstitutionDetail) {
                                        $pid = $partnerInstitutionDetail->toArray();
                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                        $newPID = new PartnerInstitutionDetails();

                                        $newPID
                                            ->setUserId($newUserId)
                                            ...
                                            ->setUpdatedAt($pid['UpdatedAt'])
                                            ->save();
                                    }
                                }

                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                $registrations = RegistrationQuery::create()->findByAppId($application['Id']);
                                $tlgTorProdIds = [394 => 347, 398 => 383, 401 => 345, 402 => 388, 403 => 353, 404 => 384, 405 => 340, 406 => 398,
                                    407 => 341, 408 => 358, 409 => 419, 411 => 410, 412 => 412, 414 => 414, 415 => 413, 416 => 415, 417 => 416, 418 => 408];
                                if (count($registrations) > 0) {
                                    foreach ($registrations as $r) {
                                        $registration = $r->toArray();
                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                        if (!RegistrationQuery::create()->findOneByExternalId($registration['Id'])) {
                                            $reg = new Registration();
                                            $reg->setUserId($newUserId)
                                                ->setProductId($tlgTorProdIds[$registration['ProductId']]);

                                            if ($registration['IntakeId']) {
                                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                                if ($oldIntake = IntakeQuery::create()->findOneById($registration['IntakeId'])) {
                                                    $oldIntakeValue = $oldIntake->toArray();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    $intake = IntakeQuery::create()
                                                        ->filterByStartDate($oldIntakeValue['StartDate'])
                                                        ->filterByActive(1)
                                                        ->findOne();
                                                    if (!$intake) {
                                                        $intake = new Intake();
                                                        $intake
                                                            ->setStartDate($oldIntakeValue['StartDate'])
                                                            ->setActive(1)
                                                            ->save();
                                                    }
                                                    $reg->setIntakeId($intake->getId());
                                                }
                                            }

                                            if ($registration['FtptId']) {
                                                $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                                if ($ftptOld = FtptQuery::create()->findOneById($registration['FtptId'])) {
                                                    $ftptOldValue = $ftptOld->toArray();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    $ftptNew = FtptQuery::create()->findOneByName($ftptOldValue['Name']);
                                                    if ($ftptNew)
                                                        $reg->setFtptId($ftptNew->getId());
                                                }
                                            }

                                            $reg
                                                ->setStartDate($registration['StartDate'])
                                                ...
                                                ->setInitialStartDate($registration['InitialStartDate'])
                                                ->save();

                                            //DISCOUNT
                                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);

                                            if ($discounts = DiscountQuery::create()->findByRegistrationId($registration['Id'])) {
                                                foreach ($discounts as $d) {
                                                    $discount = $d->toArray();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    $newDiscount = new Discount();
                                                    $newDiscount
                                                        ->setRegistrationId($reg->getId())
                                                        ...
                                                        ->setCode($discount['Code'])
                                                        ->save();
                                                }
                                            }

                                            //SCHOLARSHIP
                                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);

                                            if ($scholarships = ScholarshipQuery::create()->findByRegistrationId($registration['Id'])) {
                                                foreach ($scholarships as $s) {
                                                    $scholarship = $s->toArray();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    $newScholarship = new Scholarship();
                                                    $newScholarship
                                                        ->setRegistrationId($reg->getId())
                                                        ...
                                                        ->setPaid($scholarship['Paid'])
                                                        ->save();
                                                }
                                            }

                                            //LETTERS
                                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                            if ($letters = LetterQuery::create()->findByRegistrationId($registration['Id'])) {
                                                foreach ($letters as $l) {
                                                    $letter = $l->toArray();
                                                    $letterTemplate = LetterTemplateQuery::create()->findOneById($letter['TemplateId']);
                                                    $letterTypeTag = $letterTemplate->getLetterType()->getTag();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    $torLetterType = LetterTypeQuery::create()->findOneByTag($letterTypeTag);
                                                    $torLetterTemplate = LetterTemplateQuery::create()->findOneByLettertypeId($torLetterType->getId());
                                                    $newLetter = new Letter();
                                                    $newLetter
                                                        ->setUserId($newUserId)
                                                        ...
                                                        ->setPdfFile($letter['PdfFile'])
                                                        ->save();
                                                }
                                            }

                                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                            $transactions = TransactionQuery::create()
                                                ->filterByUserId($value['Id'])
                                                ->filterByRegistrationId($registration['Id'])
                                                ->find();
                                            if (count($transactions) > 0) {
                                                foreach ($transactions as $t) {
                                                    $transaction = $t->toArray();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    if (!TransactionQuery::create()->findOneByExternalId($transaction['Id'])) {
                                                        $newTran = new Transaction();

                                                        if ($transaction['DemandId'] !== null) {
                                                            $newTran
                                                                ->setDemandId($demandsIds[$transaction['DemandId']]);
                                                        }

                                                        $newTran
                                                            ->setAdminId($transaction['AdminId'])
                                                            ...
                                                            ->setSyncUid($transaction['SyncUid'])
                                                            ->save();

                                                        $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                                        $varReceiptAssignments = VatReceiptAssignmentQuery::create()->findByTransactionId($transaction['Id']);
                                                        if (count($varReceiptAssignments) > 0) {
                                                            foreach ($varReceiptAssignments as $vra) {
                                                                $varReceiptAssignment = $vra->toArray();
                                                                $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                                $newVRA = new VatReceiptAssignment();
                                                                $newVRA
                                                                    ->setVatReceiptId($vatReceiptIds[$varReceiptAssignment['VatReceiptId']])
                                                                    ->setTransactionId($newTran->getId())
                                                                    ->save();
                                                            }
                                                        }
                                                    }
                                                }
                                            }

                                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                                            $commissions = CommissionsCutAndPayQuery::create()
                                                ->filterByUserId($value['Id'])
                                                ->filterByRegistrationId($registration['Id'])
                                                ->find();

                                            if (count($commissions) > 0) {
                                                foreach ($commissions as $commission) {
                                                    $commissionArray = $commission->toArray();
                                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                                    $newComission = new CommissionsCutAndPay();
                                                    $newComission
                                                        ->setUserId($newUserId)
                                                        ...
                                                        ->setFinancialIncentive($commissionArray['FinancialIncentive'])
                                                        ->save();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);

                    $receipts = ReceiptQuery::create()->findByUserId($value['Id']);

                    if (count($receipts) > 0) {

                        foreach ($receipts as $receipt) {
                            $receiptArray = $receipt->toArray();
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);

                            $rec = new Receipt();
                            $rec
                                ->setUserId($newUserId)
                                ...
                                ->setVerifyCode($receiptArray['VerifyCode'])
                                ->save();
                            $rec
                                ->setRno($rec->getId())
                                ->save();

                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);
                            $receipt_assignments = ReceiptAssignmentQuery::create()->findByReceiptId($receiptArray['Id']);
                            if ($receipt_assignments) {
                                foreach ($receipt_assignments as $ra) {
                                    $receipt_assignment = $ra->toArray();
                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                    if ($transactionId = TransactionQuery::create()->findOneByExternalId($receipt_assignment['TransactionId'])) {
                                        $rec_assign = new ReceiptAssignment();
                                        $rec_assign
                                            ->setReceiptId($rec->getId())
                                            ->setTransactionId($transactionId->getId())
                                            ->save();
                                    }
                                }
                            }
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);

                    $invoices = InvoiceQuery::create()->findByUserId($value['Id']);

                    if (count($invoices) > 0) {
                        foreach ($invoices as $i) {
                            $invoice = $i->toArray();
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                            $newInvoice = new Invoice();
                            $newInvoice
                                ->setIno($invoice['Ino'])
                                ...
                                ->setPaidWithoutScholarships($invoice['PaidWithoutScholarships'])
                                ->save();

                            $this->getContainer()->get('database_switcher')->changeDatabase($data['oldDB']);

                            $invoice_assignments = InvoiceAssignmentQuery::create()->findByInvoiceId($invoice['Id']);
                            if (count($invoice_assignments) > 0) {
                                foreach ($invoice_assignments as $ia) {
                                    $invoice_assignment = $ia->toArray();
                                    $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                                    $newIA = new InvoiceAssignment();
                                    $newIA
                                        ->setInvoiceId($newInvoice->getId())
                                        ...
                                        ->setDiscountWithScholarship($invoice_assignment['DiscountWithScholarship'])
                                        ->save();
                                }
                            }
                        }
                    }

                    $this->getContainer()->get('database_switcher')->changeDatabase('global');
                    $documents = DocumentQuery::create()
                        ->filterByGlobalUserId($value['GlobalUserId'])
                        ->filterByOldCompanyId(48)
                        ->find();
                    if (count($documents) > 0) {
                        foreach ($documents as $document) {
                            $stmt = $con->prepare("UPDATE documents set old_company_id = 24 where id={$document->getId()}");
                            $stmt->execute();

                            $documentFiles = DocumentFileQuery::create()->findByDocumentId($document->getId());
                            if (count($documentFiles) > 0) {
                                foreach ($documentFiles as $documentFile) {
                                    $documentFile
                                        ->setCompanyId(24)
                                        ->save();
                                }
                            }
                        }
                    }

                    $issuedLetters = IssuedLetterQuery::create()
                        ->filterByOldCompanyId(48)
                        ->filterByGlobalUserId($value['GlobalUserId'])
                        ->find();
                    foreach ($issuedLetters as $issuedLetter) {
                        if ($issuedLetter->getApplicationId()) {
                            $this->getContainer()->get('database_switcher')->changeDatabase($data['newDB']);
                            $appForIssuedLetter = ApplicationQuery::create()->findOneByExternalId($issuedLetter->getApplicationId());
                            $this->getContainer()->get('database_switcher')->changeDatabase('global');
                            if ($appForIssuedLetter) {
                                $issuedLetter->setApplicationId($appForIssuedLetter->getId());
                            }
                        }
                        $issuedLetter
                            ->setCompanyId(24)
                            ->setOldCompanyId(24)
                            ->save();

                    }
                }
                $con->commit();
            } catch (Exception $e) {
                $con->rollBack();
                throw $e;
            }

            $this->getContainer()->get('sqs')
                ->deleteMessage($this->getQueueName(), $message['ReceiptHandle']);
        }
    }
}
