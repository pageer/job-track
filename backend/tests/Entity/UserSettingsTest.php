<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\UserSettings;
use PHPUnit\Framework\TestCase;

final class UserSettingsTest extends TestCase
{
    public function testOneDriveFoldersDefaultToAnEmptyList(): void
    {
        $settings = new UserSettings();

        $this->assertSame([], $settings->getOneDriveFolders());
        $this->assertNotNull($settings->getCreatedAt());
        $this->assertNotNull($settings->getUpdatedAt());
    }

    public function testSetOneDriveFoldersStoresTheListAndUpdatesTheTimestamp(): void
    {
        $settings = new UserSettings();
        $before = $settings->getUpdatedAt();

        $settings->setOneDriveFolders(['Resumes', 'Old Resumes']);

        $this->assertSame(['Resumes', 'Old Resumes'], $settings->getOneDriveFolders());
        $this->assertNotSame($before, $settings->getUpdatedAt());
    }

    public function testUserRelationship(): void
    {
        $user = new User();
        $settings = new UserSettings();
        $settings->setUser($user);

        $this->assertSame($user, $settings->getUser());
    }
}
