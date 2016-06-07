<?php
namespace PromisePay\Tests;

use PromisePay\PromisePay;

class WalletAccountsTest extends \PHPUnit_Framework_TestCase {
    
    protected $GUID, $userData;
    
    public function setUp() {
        $this->GUID = GUID();
        
        $this->userData = array(
            'id'            => $this->GUID,
            'first_name'    => 'UserCreateTest',
            'last_name'     => 'UserLastname',
            'email'         => $this->GUID . '@google.com',
            'mobile'        => $this->GUID . '00012',
            'address_line1' => 'a_line1',
            'address_line2' => 'a_line2',
            'state'         => 'state',
            'city'          => 'city',
            'zip'           => '90210',
            'country'       => 'AUS'
        );
    }
    
    protected function regenUserData() {
        $guid = GUID();
        
        $this->userData['id'] = $guid;
        $this->userData['email'] = $guid . '@testing.com';
        $this->userData['mobile'] = $guid . '00012';
    }
    
    protected function createUser() {
        $user = PromisePay::User()->create($this->userData);
        
        $this->assertEquals($this->userData['email'], $user['email']);
        
        return $user;
    }
    
    protected function createBankAccount($uid) {
        require_once __DIR__ . '/BankAccountTest.php';
        
        $bankAccountTest = new BankAccountTest;
        
        $bankAccountTest->setUp();
        $bankAccountTest->setBankAccountUserId($uid);
        
        return $bankAccountTest->testCreateBankAccount();
    }
    
    public function testShow() {
        $user = $this->createUser();
        $bank = $this->createBankAccount($user['id']);
        
        $wallet = PromisePay::WalletAccounts()->show($bank['id']);
        
        $this->assertNotNull($wallet);
        $this->assertEquals($bank['id'], $wallet['id']);
    }
    
    public function testGetUser() {
        $user = $this->createUser();
        $bank = $this->createBankAccount($user['id']);
        
        $walletUser = PromisePay::WalletAccounts()->getUser(
            $bank['id']
        );
        
        $this->assertNotNull($walletUser);
        $this->assertEquals($user['email'], $walletUser['email']);
        $this->assertEquals($user['first_name'], $walletUser['first_name']);
        $this->assertEquals($user['last_name'], $walletUser['last_name']);
    }
    
    public function testDeposit() {
        // USING 1 USER, 2 BANK ACCOUNTS AND DIRECT DEBIT AUTHORITY
        $user = $this->createUser();
        
        // add KYC (Know Your Customer) properties
        $user = PromisePay::User()->update(
            $user['id'],
            array(
                'government_number'      => 123456782,
                'phone'                  => '+1234567889',
                'dob'                    => '30/01/1990',
                'drivers_license_number' => '123456789',
                'drivers_license_state'  => 'NSW'
            )
        );
        
        $this->assertNotNull($user);
        
        $bankReceiving = $this->createBankAccount($user['id']);
        $bankSending = $this->createBankAccount($user['id']);
        
        $this->assertNotNull($bankReceiving);
        $this->assertNotNull($bankSending);
        
        $depositAmount = 1000;
        
        $bankSendingAuthority = PromisePay::DirectDebitAuthority()->create(
            array(
                'account_id' => $bankSending['id'],
                'amount'     => $depositAmount
            )
        );
        
        $this->assertNotNull($bankSendingAuthority);
        
        $deposit = PromisePay::WalletAccounts()->deposit(
            $bankReceiving['id'],
            array(
                'account_id' => $bankSending['id'],
                'amount'     => $depositAmount
            )
        );
        
        $this->assertNotNull($deposit);
        
        $this->assertEquals($deposit['amount'], $depositAmount);
        $this->assertEquals($deposit['currency'], $bankReceiving['currency']);
        $this->assertEquals($deposit['to'], 'Bank Account');
        $this->assertEquals($deposit['bank_name'], $bankReceiving['bank']['bank_name']);
        $this->assertEquals($deposit['bank_account_number'], $bankReceiving['bank']['account_number']);
        
        return array(
            'user'                            => $user,
            'bankReceiving'                   => $bankReceiving,
            'bankSending'                     => $bankSending,
            'bankSendingDirectDebitAuthority' => $bankSendingAuthority,
            'deposit'                         => $deposit
        );
    }
    
    public function testWithdrawToPayPal() {
        // WITHDRAW TO PAYPAL
        extract($this->testDeposit());
        
        $withdrawAmount = 100;
        
        $payPalAccount = PromisePay::PayPalAccount()->create(
            array(
                'user_id'      => $user['id'],
                'paypal_email' => $user['email']
            )
        );
        
        $bankSendingAuthority = PromisePay::DirectDebitAuthority()->create(
            array(
                'account_id' => $bankReceiving['id'],
                'amount'     => $withdrawAmount
            )
        );
        
        $withdraw = PromisePay::WalletAccounts()->withdraw(
            $bankReceiving['id'],
            array(
                'account_id' => $payPalAccount['id'],
                'amount'     => $withdrawAmount
            )
        );
        
        $this->assertNotNull($withdraw);
        $this->assertEquals($withdraw['amount'], $withdrawAmount);
        $this->assertEquals(
            trim($withdraw['to']), // API currently returns "PayPal Disbursement "
            'PayPal Disbursement'
        );
        $this->assertEquals($withdraw['paypal_email'], $user['email']);
    }
}