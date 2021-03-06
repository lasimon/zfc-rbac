<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfcRbacTest\Service;

use Zend\Permissions\Rbac\Rbac;
use ZfcRbac\Identity\IdentityInterface;
use ZfcRbac\Role\InMemoryRoleProvider;
use ZfcRbac\Service\AuthorizationService;
use ZfcRbac\Service\RoleService;
use ZfcRbacTest\Asset\SimpleAssertion;

/**
 * @covers \ZfcRbac\Service\AuthorizationService
 */
class AuthorizationServiceTest extends \PHPUnit_Framework_TestCase
{
    public function grantedProvider()
    {
        return [
            // Simple is granted
            [
                'guest',
                'read',
                null,
                true
            ],

            // Simple is allowed from parent
            [
                'member',
                'read',
                null,
                true
            ],

            // Simple is refused
            [
                'guest',
                'write',
                null,
                false
            ],

            // Simple is refused from parent
            [
                'guest',
                'delete',
                null,
                false
            ],

            // Simple is refused from dynamic assertion
            [
                'member',
                'read',
                function() { return false; },
                false
            ],

            // Simple is accepted with dynamic assertion
            [
                'member',
                'read',
                function() { return true; },
                true
            ],

            // Simple is refused from no role
            [
                [],
                'read',
                null,
                false
            ],
        ];
    }

    /**
     * @dataProvider grantedProvider
     */
    public function testGranted($role, $permission, $assertion = null, $isGranted)
    {
        $roleConfig = [
            'admin' => [
                'children'    => ['member'],
                'permissions' => ['delete']
            ],
            'member' => [
                'children'    => ['guest'],
                'permissions' => ['write']
            ],
            'guest' => [
                'permissions' => ['read']
            ]
        ];

        $identity = $this->getMock('ZfcRbac\Identity\IdentityInterface');
        $identity->expects($this->once())->method('getRoles')->will($this->returnValue((array) $role));

        $identityProvider = $this->getMock('ZfcRbac\Identity\IdentityProviderInterface');
        $identityProvider->expects($this->any())
                         ->method('getIdentity')
                         ->will($this->returnValue($identity));

        $roleService = new RoleService($identityProvider, new InMemoryRoleProvider($roleConfig));

        $authorizationService = new AuthorizationService($roleService);

        $this->assertEquals($isGranted, $authorizationService->isGranted($permission, $assertion));
    }

    public function testThrowExceptionForInvalidAssertion()
    {
        $roleService = $this->getMock('ZfcRbac\Service\RoleService', [], [], '', false);
        $roleService->expects($this->once())->method('getIdentityRoles')->will($this->returnValue([
            $this->getMock('Rbac\Role\RoleInterface')
        ]));

        $authorizationService = new AuthorizationService($roleService);

        $this->setExpectedException('ZfcRbac\Exception\InvalidArgumentException');

        $authorizationService->isGranted('foo', new \stdClass());
    }

    public function testDynamicAssertions()
    {
        $identity = $this->getMock('ZfcRbac\Identity\IdentityInterface');

        $roleService = $this->getMock('ZfcRbac\Service\RoleService', [], [], '', false);
        $roleService->expects($this->exactly(2))->method('getIdentity')->will($this->returnValue($identity));
        $roleService->expects($this->exactly(2))->method('getIdentityRoles')->will($this->returnValue([
            $this->getMock('Rbac\Role\RoleInterface')
        ]));

        $authorizationService = new AuthorizationService($roleService);

        // Using a callable
        $called = false;
        $this->assertFalse($authorizationService->isGranted('foo',
                function(IdentityInterface $expectedIdentity = null) use($identity, &$called) {
                    $this->assertSame($expectedIdentity, $identity);
                    $called = true;

                    return false;
                })
        );
        $this->assertTrue($called);

        // Using an assertion object
        $assertion = new SimpleAssertion();
        $this->assertFalse($authorizationService->isGranted('foo', $assertion));
        $this->assertTrue($assertion->getCalled());
    }
}
