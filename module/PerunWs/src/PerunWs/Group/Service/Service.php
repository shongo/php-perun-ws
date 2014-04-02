<?php

namespace PerunWs\Group\Service;

use PerunWs\Perun\Service\AbstractService;
use InoPerunApi\Manager\GenericManager;
use InoPerunApi\Entity;
use InoPerunApi\Manager\Exception\PerunErrorException;
use InoPerunApi\Entity\Collection\GroupCollection;


/**
 * Implementation of the group service interface.
 */
class Service extends AbstractService implements ServiceInterface
{

    const PERUN_EXCEPTION_GROUP_NOT_EXISTS = 'GroupNotExistsException';

    const PERUN_EXCEPTION_USER_NOT_EXISTS = 'UserNotExistsException';

    const PERUN_EXCEPTION_USER_ALREADY_ADMIN = 'AlreadyAdminException';

    const PERUN_EXCEPTION_USER_NOT_ADMIN = 'UserNotAdminException';

    /**
     * The name of the group manager (remote APi object).
     * 
     * @see http://perun.metacentrum.cz/javadoc/cz/metacentrum/perun/core/api/GroupsManager.html
     * @var string
     */
    protected $groupsManagerName = 'groupsManager';

    /**
     * The name of the members manager (remote API object).
     * 
     * @see http://perun.metacentrum.cz/javadoc/cz/metacentrum/perun/core/api/MembersManager.html
     * @var string
     */
    protected $membersManagerName = 'membersManager';

    /**
     * @var GenericManager
     */
    protected $groupsManager;

    /**
     * @var GenericManager
     */
    protected $membersManager;

    /**
     * @var Entity\Factory\FactoryInterface
     */
    protected $entityFactory;


    /**
     * @return string
     */
    public function getGroupsManagerName()
    {
        return $this->groupsManagerName;
    }


    /**
     * @param string $groupsManagerName
     */
    public function setGroupsManagerName($groupsManagerName)
    {
        $this->groupsManagerName = $groupsManagerName;
    }


    /**
     * @return string
     */
    public function getMembersManagerName()
    {
        return $this->membersManagerName;
    }


    /**
     * @param string $membersManagerName
     */
    public function setMembersManagerName($membersManagerName)
    {
        $this->membersManagerName = $membersManagerName;
    }


    /**
     * @return GenericManager
     */
    public function getGroupsManager()
    {
        if (! $this->groupsManager instanceof GenericManager) {
            $this->groupsManager = $this->createManager($this->groupsManagerName);
        }
        return $this->groupsManager;
    }


    /**
     * @param GenericManager $groupsManager
     */
    public function setGroupsManager(GenericManager $groupsManager)
    {
        $this->groupsManager = $groupsManager;
    }


    /**
     * @return GenericManager
     */
    public function getMembersManager()
    {
        if (! $this->membersManager instanceof GenericManager) {
            $this->membersManager = $this->createManager($this->membersManagerName);
        }
        return $this->membersManager;
    }


    /**
     * @param GenericManager $membersManager
     */
    public function setMembersManager(GenericManager $membersManager)
    {
        $this->membersManager = $membersManager;
    }


    /**
     * @return Entity\Factory\FactoryInterface
     */
    public function getEntityFactory()
    {
        if (! $this->entityFactory instanceof Entity\Factory\FactoryInterface) {
            $this->entityFactory = new Entity\Factory\GenericFactory();
        }
        return $this->entityFactory;
    }


    /**
     * @param Entity\Factory\FactoryInterface $entityFactory
     */
    public function setEntityFactory(Entity\Factory\FactoryInterface $entityFactory)
    {
        $this->entityFactory = $entityFactory;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::fetchAll()
     */
    public function fetchAll(array $params = array())
    {
        $params['vo'] = $this->getVoId();
        
        if (isset($params['filter_group_id']) && is_array($params['filter_group_id'])) {
            return $this->fetchByMultipleId($params['filter_group_id']);
        }
        
        // $groups = $this->getGroupsManager()->getGroups($params);
        $groups = $this->getGroupsManager()->getSubGroups(array(
            'parentGroup' => $this->getBaseGroupId()
        ));
        
        return $groups;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::fetch()
     */
    public function fetch($id)
    {
        $groupsManager = $this->getGroupsManager();
        
        try {
            $group = $groupsManager->getGroupById(array(
                'id' => $id
            ));
            
            // Check if group is a subgroup of the base group
            if ($group->getParentGroupId() !== $this->getBaseGroupId()) {
                return null;
            }
            
            $admins = $groupsManager->getAdmins(array(
                'group' => $id
            ));
            $group->setAdmins($admins);
        } catch (PerunErrorException $e) {
            if (self::PERUN_EXCEPTION_GROUP_NOT_EXISTS == $e->getErrorName()) {
                return null;
            }
            
            throw new Exception\GroupRetrievalException(sprintf("[%s] %s", $e->getErrorName(), $e->getErrorMessage()), 400, $e);
        }
        
        return $group;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::create()
     */
    public function create($data)
    {
        if (! property_exists($data, 'name')) {
            throw new Exception\GroupCreationException("Missing field 'name'", 400);
        }
        
        $group = $this->getEntityFactory()->createEntityWithName('Group', array(
            'name' => $data->name,
            'description' => property_exists($data, 'description') ? $data->description : '',
            'parentGroupId' => $this->getBaseGroupId()
        ));
        
        try {
            $newGroup = $this->getGroupsManager()->createGroup(array(
                'vo' => $this->getVoId(),
                'group' => $group
            ));
        } catch (PerunErrorException $e) {
            throw new Exception\GroupCreationException(sprintf("[%s] %s", $e->getErrorName(), $e->getErrorMessage()), 400, $e);
        }
        
        return $newGroup;
    }


    /**
     * FIXME
     * Currently it doesn't work due to privilege exception:
     * Error 14319175fa6: Principal /C=CZ/O=CESNET/CN=hroch.cesnet.cz is not authorized to perform action 'updateGroup'
     * 
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::patch()
     */
    public function patch($id, $data)
    {
        if (! property_exists($data, 'name')) {
            throw new Exception\GroupCreationException("Missing field 'name'", 400);
        }
        
        $groupData = array(
            'id' => $id,
            'name' => $data->name,
            'description' => $data->description
        );
        
        $group = $this->getGroupsManager()->updateGroup(array(
            'group' => $groupData
        ));
        
        return $group;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::delete()
     */
    public function delete($id)
    {
        try {
            $this->getGroupsManager()->deleteGroup(array(
                'group' => $id
            ));
        } catch (PerunErrorException $e) {
            throw new Exception\GroupDeleteException(sprintf("[%s] %s", $e->getErrorName(), $e->getErrorMessage()), 400, $e);
        }
        
        return true;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::fetchMembers()
     */
    public function fetchMembers($id)
    {
        try {
            $members = $this->getGroupsManager()->getGroupRichMembers(array(
                'group' => $id
            ));
        } catch (PerunErrorException $e) {
            if (self::PERUN_EXCEPTION_GROUP_NOT_EXISTS == $e->getErrorName()) {
                throw new Exception\GroupRetrievalException(sprintf("Group ID:%d not found", $id), null, $e);
            }
            throw $e;
        }
        
        return $members;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::fetchUserGroups()
     */
    public function fetchUserGroups($userId)
    {
        $member = $this->getMemberByUser($userId);
        $groups = $this->getGroupsManager()->getAllMemberGroups(array(
            'member' => $member->getId()
        ));
        
        return $groups;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::addUserToGroup()
     */
    public function addUserToGroup($userId, $groupId)
    {
        $member = $this->getMemberByUser($userId);
        $this->getGroupsManager()->addMember(array(
            'group' => $groupId,
            'member' => $member->getId()
        ));
        
        return $member;
    }


    /**
     * {@inheritdoc}
     * @see \PerunWs\Group\Service\ServiceInterface::removeUserFromGroup()
     */
    public function removeUserFromGroup($userId, $groupId)
    {
        $member = $this->getMemberByUser($userId);
        $this->getGroupsManager()->removeMember(array(
            'group' => $groupId,
            'member' => $member->getId()
        ));
        
        return true;
    }


    public function fetchGroupAdmins($groupId)
    {
        try {
            $users = $this->getGroupsManager()->getAdmins(array(
                'group' => $groupId
            ));
        } catch (PerunErrorException $e) {
            throw new Exception\GroupGenericException(sprintf("[%s] %s", $e->getErrorName(), $e->getErrorMessage()), 400, $e);
        }
        
        return $users;
    }


    /**
     * {@inhertidoc}
     * @see \PerunWs\Group\Service\ServiceInterface::addGroupAdmin()
     */
    public function addGroupAdmin($groupId, $userId)
    {
        try {
            $this->getGroupsManager()->addAdmin(array(
                'group' => $groupId,
                'user' => $userId
            ));
        } catch (PerunErrorException $e) {
            throw new Exception\GroupGenericException(sprintf("[%s] %s", $e->getErrorName(), $e->getErrorMessage()), 400, $e);
        }
        
        return true;
    }


    /**
     * {@inhertidoc}
     * @see \PerunWs\Group\Service\ServiceInterface::removeGroupAdmin()
     */
    public function removeGroupAdmin($groupId, $userId)
    {
        try {
            $this->getGroupsManager()->removeAdmin(array(
                'group' => $groupId,
                'user' => $userId
            ));
        } catch (PerunErrorException $e) {
            throw new Exception\GroupGenericException(sprintf("[%s] %s", $e->getErrorName(), $e->getErrorMessage()), 400, $e);
        }
        
        return true;
    }


    /**
     * Retrieves the user's corresponding "member" entity.
     * 
     * @param integer $userId
     * @return \InoPerunApi\Entity\Member|null
     */
    public function getMemberByUser($userId)
    {
        try {
            $member = $this->getMembersManager()->getMemberByUser(array(
                'vo' => $this->getVoId(),
                'user' => $userId
            ));
        } catch (PerunErrorException $e) {
            if (self::PERUN_EXCEPTION_USER_NOT_EXISTS == $e->getErrorName()) {
                throw new Exception\MemberRetrievalException(sprintf("User ID:%d not found", $userId));
            }
            throw $e;
        }
        
        return $member;
    }


    /**
     * Returns a collection of groups by specific IDs.
     * 
     * @param array $groupIdList
     * @return GroupCollection
     */
    public function fetchByMultipleId(array $groupIdList)
    {
        $groups = new GroupCollection();
        
        foreach ($groupIdList as $groupId) {
            $group = $this->fetch($groupId);
            if (null !== $group) {
                $groups->append($group);
            }
        }
        
        return $groups;
    }
}