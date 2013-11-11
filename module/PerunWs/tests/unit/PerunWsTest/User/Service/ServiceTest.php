<?php

namespace PerunWsTest\User\Service;

use PerunWs\User\Service\Service;
use InoPerunApi\Manager\Exception\PerunErrorException;
use Zend\Stdlib\Parameters;


class ServiceTest extends \PHPUnit_Framework_TestCase
{

    protected $service;


    public function setUp()
    {
        $parameters = $this->getMock('Zend\Stdlib\Parameters');
        $parameters->expects($this->any())
            ->method('get')
            ->with('principal_names_attribute_name')
            ->will($this->returnValue('principalNames'));
        
        $this->service = new Service($parameters);
    }


    public function testGetUsersManagerWithImplicitValue()
    {
        $managerName = 'fooManager';
        $manager = $this->getManagerMock();
        
        $service = $this->getServiceMockWithManagerName($managerName, $manager);
        $service->setUsersManagerName($managerName);
        
        $this->assertSame($manager, $service->getUsersManager());
    }


    public function testGetMembersManagerWithImplicitValue()
    {
        $managerName = 'barManager';
        $manager = $this->getManagerMock();
        
        $service = $this->getServiceMockWithManagerName($managerName, $manager);
        $service->setMembersManagerName($managerName);
        
        $this->assertSame($manager, $service->getMembersManager());
    }


    public function testFetchWithNotExistsException()
    {
        $id = 123;
        $params = array(
            'user' => $id
        );
        $exception = new PerunErrorException();
        $exception->setErrorName(Service::PERUN_EXCEPTION_USER_NOT_EXISTS);
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('getRichUserWithAttributes')
            ->with($params)
            ->will($this->throwException($exception));
        
        $this->service->setUsersManager($manager);
        $this->assertNull($this->service->fetch($id));
    }


    public function testFetchWithGeneralException()
    {
        $this->setExpectedException('InoPerunApi\Manager\Exception\PerunErrorException');
        
        $id = 123;
        $params = array(
            'user' => $id
        );
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('getRichUserWithAttributes')
            ->with($params)
            ->will($this->throwException(new PerunErrorException('perun exception')));
        
        $this->service->setUsersManager($manager);
        $this->service->fetch($id);
    }


    public function testFetch()
    {
        $id = 123;
        $params = array(
            'user' => $id
        );
        $user = $this->getUserMock();
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('getRichUserWithAttributes')
            ->with($params)
            ->will($this->returnValue($user));
        
        $this->service->setUsersManager($manager);
        $this->assertSame($user, $this->service->fetch($id));
    }


    public function testFetchAll()
    {
        $voId = 123;
        $serviceParams = array(
            'vo_id' => $voId
        );
        $callParams = array(
            'vo' => $voId
        );
        $users = $this->getUserCollectionMock();
        
        $this->service->setParameters(new Parameters($serviceParams));
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('getRichMembersWithAttributes')
            ->with($callParams)
            ->will($this->returnValue($users));
        $this->service->setMembersManager($manager);
        
        $this->assertSame($users, $this->service->fetchAll());
    }


    public function testFetchAllWithSearchString()
    {
        $voId = 123;
        $searchString = 'foo';
        $serviceParams = array(
            'vo_id' => $voId
        );
        
        $callParams = array(
            'vo' => $voId,
            'searchString' => $searchString
        );
        $users = $this->getUserCollectionMock();
        
        $this->service->setParameters(new Parameters($serviceParams));
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('findRichMembersWithAttributesInVo')
            ->with($callParams)
            ->will($this->returnValue($users));
        $this->service->setMembersManager($manager);
        
        $this->assertSame($users, $this->service->fetchAll(array(
            'searchString' => $searchString
        )));
    }


    public function testFetchByPrincipalNameWithNotFound()
    {
        $principalName = 'foo';
        $params = array(
            'attributeName' => $this->service->getPrincipalNamesAttributeName(),
            'attributeValue' => $principalName
        );
        $users = $this->getUserCollectionMock();
        $users->expects($this->once())
            ->method('count')
            ->will($this->returnValue(0));
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('getUsersByAttributeValue')
            ->with($params)
            ->will($this->returnValue($users));
        $this->service->setUsersManager($manager);
        
        $this->assertNull($this->service->fetchByPrincipalName($principalName));
    }


    public function testFetchByPrincipalNameWithMultipleFound()
    {
        $this->setExpectedException('PerunWs\User\Service\Exception\MultipleUsersPerPrincipalNameException');
        
        $principalName = 'foo';
        $params = array(
            'attributeName' => $this->service->getPrincipalNamesAttributeName(),
            'attributeValue' => $principalName
        );
        $users = $this->getUserCollectionMock();
        $users->expects($this->any())
            ->method('count')
            ->will($this->returnValue(2));
        
        $manager = $this->getManagerMock();
        $manager->expects($this->once())
            ->method('getUsersByAttributeValue')
            ->with($params)
            ->will($this->returnValue($users));
        $this->service->setUsersManager($manager);
        
        $this->service->fetchByPrincipalName($principalName);
    }
    
    /*
     * 
     */
    
    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function getManagerMock()
    {
        $manager = $this->getMockBuilder('InoPerunApi\Manager\GenericManager')
            ->disableOriginalConstructor()
            ->setMethods(array(
            'getRichUserWithAttributes',
            'getRichMembersWithAttributes',
            'findRichMembersWithAttributesInVo',
            'getUsersByAttributeValue'
        ))
            ->getMock();
        return $manager;
    }


    protected function getServiceMockWithManagerName($managerName, $manager)
    {
        $service = $this->getMockBuilder('PerunWs\User\Service\Service')
            ->disableOriginalConstructor()
            ->setMethods(array(
            'createManager'
        ))
            ->getMock();
        
        $service->expects($this->once())
            ->method('createManager')
            ->with($managerName)
            ->will($this->returnValue($manager));
        
        return $service;
    }


    protected function getUserMock()
    {
        $user = $this->getMock('InoPerunApi\Entity\User');
        return $user;
    }


    protected function getUserCollectionMock()
    {
        $userCollection = $this->getMock('InoPerunApi\Entity\Collection\UserCollection');
        return $userCollection;
    }
}