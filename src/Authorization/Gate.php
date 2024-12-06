<?php


namespace Zfegg\Admin\Admin\Authorization;

use Doctrine\ORM\EntityManagerInterface;
use Mezzio\Authentication\UserInterface;
use Zfegg\Admin\Admin\Entity\Role;
use Zfegg\Admin\Admin\Entity\RoleMenu;

class Gate implements GateInterface
{

    private array $menus;

    public function __construct(
        private EntityManagerInterface $em,
        array $menus
    ) {
        $this->menus = self::flatMenus($menus);
    }

    private static function flatMenus(array $menus, array $parents = [], array $parentPermissions = []): array
    {
        $result = [];
        foreach ($menus as $menu) {
            $keys = array_merge($parents, [$menu['name']]);
            $key = implode('/', $keys);
            $permissions = array_merge($parentPermissions, $menu['permissions'] ?? []);
            $result[$key] = $permissions;

            if (! empty($menu['children'])) {
                $result += self::flatMenus($menu['children'], $keys, $permissions);
            }
        }

        return $result;
    }

    public function isGranted(UserInterface $user, string $permission): bool
    {
        foreach ($user->getRoles() as $role) {
            if ($this->isGrantedByRole($role->getId(), $permission)) {
                return true;
            }
        }

        return false;
    }

    public function isGrantedByRole(string $role, string $permission): bool
    {
        $em = $this->em;
        $role = $em->find(Role::class, $role);

        if (! $role) {
            return false;
        }

        $q = $em->createQuery(sprintf('SELECT rm.menu FROM %s rm WHERE rm.role=?0', RoleMenu::class));
        $q->setParameters([$role]);

        $menus = $q->getSingleColumnResult();

        foreach ($menus as $menu) {
            if (! isset($this->menus[$menu])) {
                continue;
            }

            foreach ($this->menus[$menu] as $permissionPattern) {
                if (fnmatch($permissionPattern, $permission)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取用户所有菜单
     *
     * @return string[]
     */
    public function userMenus(int $userId): array
    {

        $q = $this->em->createQuery(
            sprintf(
                "SELECT rm.menu FROM %s rm JOIN rm.role r WHERE ?0 MEMBER OF r.users",
                RoleMenu::class
            )
        );
        $q->setParameters([$userId]);

        return $q->getSingleColumnResult();
    }
}
