<?php

namespace Zfegg\Admin\Admin\Repository;

use Doctrine\ORM\EntityRepository;
use Zfegg\Admin\Admin\Entity\User;

class Users extends EntityRepository
{

    public function validate(string $email, string $password): ?User
    {
        $dql = sprintf("SELECT u FROM %s u WHERE u.email=?1 and u.status >= 1", User::class);
        $qb = $this->getEntityManager()->createQuery($dql)
            ->setParameter(1, $email);

        /** @var \zfegg\Admin\Admin\Entity\User $user */
        if (! $user = $qb->getOneOrNullResult()) {
            return null;
        }

        if (! $user->isValidPassword($password)) {
            return null;
        }

        return $user;
    }
}
