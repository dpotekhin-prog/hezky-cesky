<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[UniqueEntity(fields: ['username'], message: 'user.username_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_CHILD = 'ROLE_CHILD';
    public const ROLE_PARENT = 'ROLE_PARENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 30)]
    #[Assert\Regex(pattern: '/^[\p{L}0-9_.-]+$/u', message: 'user.username_chars')]
    private string $username = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [self::ROLE_CHILD];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Code a child gives to a parent so the parent can link the account. */
    #[ORM\Column(length: 12, unique: true, nullable: true)]
    private ?string $pairingCode = null;

    /** Parents (a child may have two). @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinTable(name: 'parent_child')]
    #[ORM\JoinColumn(name: 'child_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'parent_id', onDelete: 'CASCADE')]
    private Collection $parents;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'parents')]
    #[ORM\OrderBy(['username' => 'ASC'])]
    private Collection $children;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->regeneratePairingCode();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = trim($username);

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isParent(): bool
    {
        return in_array(self::ROLE_PARENT, $this->roles, true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPairingCode(): ?string
    {
        return $this->pairingCode;
    }

    public function regeneratePairingCode(): void
    {
        // No 0/O/1/I so the code is easy to read out to a parent.
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; ++$i) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $this->pairingCode = $code;
    }

    /** @return Collection<int, User> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /** @return Collection<int, User> */
    public function getParents(): Collection
    {
        return $this->parents;
    }

    public function isChildOf(User $parent): bool
    {
        return $this->parents->contains($parent);
    }

    public function addParent(User $parent): void
    {
        if (!$this->parents->contains($parent)) {
            $this->parents->add($parent);
            $parent->children->add($this);
        }
    }

    /**
     * Only what the security layer needs goes into the session (the user is reloaded on every request);
     * the password is stored as a checksum so a changed password logs out other sessions.
     */
    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'roles' => $this->roles,
            'password' => hash('crc32c', $this->password),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->username = $data['username'];
        $this->roles = $data['roles'];
        $this->password = $data['password'];
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }
}
