<?php

namespace App\Tests\Unit;

use App\Entity\OrdreReparation;
use App\Entity\RendezVous;
use App\Entity\User;
use App\Service\OrdreReparationPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class OrdreReparationPolicyTest extends TestCase
{
    private OrdreReparationPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new OrdreReparationPolicy();
    }

    private function createUser(string $role = 'receptionnaire'): User
    {
        $user = new User();
        $user->setUsername('test_' . $role);
        $user->setEmail($role . '@test.local');
        $user->setPassword('hashed');
        $user->setRole($role);
        return $user;
    }

    private function createOR(string $statut = 'brouillon'): OrdreReparation
    {
        $rdv = new RendezVous();
        $or = new OrdreReparation();
        $or->setRendezVous($rdv);
        $or->setNumeroOr('OR-TEST-001');
        $or->setStatut($statut);
        $or->setTravaux('Test travaux');
        return $or;
    }

    // ─── canEdit tests ───

    public function testCanEditBrouillonAsReceptionnaire(): void
    {
        $or = $this->createOR('brouillon');
        $user = $this->createUser('receptionnaire');
        $this->assertTrue($this->policy->canEdit($or, $user));
    }

    public function testCanEditBrouillonAsAdmin(): void
    {
        $or = $this->createOR('brouillon');
        $user = $this->createUser('admin');
        $this->assertTrue($this->policy->canEdit($or, $user));
    }

    public function testCanEditBrouillonAsMecanicien(): void
    {
        $or = $this->createOR('brouillon');
        $user = $this->createUser('mecanicien');
        $this->assertTrue($this->policy->canEdit($or, $user));
    }

    public function testCannotEditSignedOR(): void
    {
        $or = $this->createOR('signe');
        $user = $this->createUser('receptionnaire');
        $this->assertFalse($this->policy->canEdit($or, $user));
    }

    public function testCannotEditTerminedOR(): void
    {
        $or = $this->createOR('termine');
        $user = $this->createUser('admin');
        $this->assertFalse($this->policy->canEdit($or, $user));
    }

    // ─── canSign tests ───

    public function testCanSignBrouillon(): void
    {
        $or = $this->createOR('brouillon');
        $this->assertTrue($this->policy->canSign($or));
    }

    public function testCannotSignEnAttenteSignature(): void
    {
        $or = $this->createOR('en_attente_signature');
        $this->assertFalse($this->policy->canSign($or));
    }

    public function testCannotSignAlreadySigned(): void
    {
        $or = $this->createOR('signe');
        $this->assertFalse($this->policy->canSign($or));
    }

    public function testCannotSignExecute(): void
    {
        $or = $this->createOR('execute');
        $this->assertFalse($this->policy->canSign($or));
    }

    // ─── canRectify tests ───

    public function testCanRectifyAsAdmin(): void
    {
        $or = $this->createOR('signe');
        $or->setSignedHash('abc');
        $or->setSignedSnapshot(['test' => true]);
        $or->setSignedAt(new \DateTime());
        $user = $this->createUser('admin');
        $this->assertTrue($this->policy->canRectify($or, $user));
    }

    public function testCanRectifyAsResponsableAtelier(): void
    {
        $or = $this->createOR('signe');
        $or->setSignedHash('abc');
        $or->setSignedSnapshot(['test' => true]);
        $or->setSignedAt(new \DateTime());
        $user = $this->createUser('responsable_atelier');
        $this->assertTrue($this->policy->canRectify($or, $user));
    }

    public function testCannotRectifyAsMecanicien(): void
    {
        $or = $this->createOR('signe');
        $or->setSignedHash('abc');
        $or->setSignedSnapshot(['test' => true]);
        $or->setSignedAt(new \DateTime());
        $user = $this->createUser('mecanicien');
        $this->assertFalse($this->policy->canRectify($or, $user));
    }

    public function testCannotRectifyUnsigned(): void
    {
        $or = $this->createOR('brouillon');
        $user = $this->createUser('admin');
        $this->assertFalse($this->policy->canRectify($or, $user));
    }

    // ─── canAddComplementaire tests ───

    public function testCanAddComplementaireOnSignedOR(): void
    {
        $or = $this->createOR('signe');
        $user = $this->createUser('mecanicien');
        $this->assertTrue($this->policy->canAddComplementaire($or, $user));
    }

    public function testCanAddComplementaireOnExecuteOR(): void
    {
        $or = $this->createOR('execute');
        $user = $this->createUser('mecanicien');
        $this->assertTrue($this->policy->canAddComplementaire($or, $user));
    }

    public function testCannotAddComplementaireOnBrouillon(): void
    {
        $or = $this->createOR('brouillon');
        $user = $this->createUser('mecanicien');
        $this->assertFalse($this->policy->canAddComplementaire($or, $user));
    }

    public function testCannotAddComplementaireOnTermine(): void
    {
        $or = $this->createOR('termine');
        $user = $this->createUser('mecanicien');
        $this->assertFalse($this->policy->canAddComplementaire($or, $user));
    }

    // ─── buildSnapshot + computeHash + verifyIntegrity ───

    public function testBuildSnapshotContainsRequiredFields(): void
    {
        $or = $this->createOR('brouillon');
        $snapshot = $this->policy->buildSnapshot($or);

        $this->assertArrayHasKey('numero_or', $snapshot);
        $this->assertArrayHasKey('type_or', $snapshot);
        $this->assertArrayHasKey('travaux', $snapshot);
        $this->assertArrayHasKey('created_at', $snapshot);
        $this->assertEquals('OR-TEST-001', $snapshot['numero_or']);
    }

    public function testComputeHashIsDeterministic(): void
    {
        $snapshot = ['a' => 1, 'b' => 2];
        $hash1 = $this->policy->computeHash($snapshot);
        $hash2 = $this->policy->computeHash($snapshot);
        $this->assertSame($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 hex length
    }

    public function testComputeHashDiffersForDifferentData(): void
    {
        $hash1 = $this->policy->computeHash(['a' => 1]);
        $hash2 = $this->policy->computeHash(['a' => 2]);
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testSignSetsAllFields(): void
    {
        $or = $this->createOR('brouillon');
        $request = Request::create('/test', 'POST');
        $signatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEA';

        $hash = $this->policy->sign($or, $signatureData, $request);

        $this->assertNotEmpty($hash);
        $this->assertSame('signe', $or->getStatut());
        $this->assertSame($signatureData, $or->getSignatureClient());
        $this->assertNotNull($or->getSignedSnapshot());
        $this->assertNotNull($or->getSignedAt());
    }

    public function testVerifyIntegrityAfterSign(): void
    {
        $or = $this->createOR('brouillon');
        $request = Request::create('/test', 'POST');
        $this->policy->sign($or, 'data:image/png;base64,abc', $request);

        $this->assertTrue($this->policy->verifyIntegrity($or));
    }

    public function testVerifyIntegrityFailsWhenTampered(): void
    {
        $or = $this->createOR('brouillon');
        $request = Request::create('/test', 'POST');
        $this->policy->sign($or, 'data:image/png;base64,abc', $request);

        // Tamper with the snapshot
        $or->setSignedSnapshot(['tampered' => true]);

        $this->assertFalse($this->policy->verifyIntegrity($or));
    }

    public function testVerifyIntegrityFalseWhenNotSigned(): void
    {
        $or = $this->createOR('brouillon');
        $this->assertFalse($this->policy->verifyIntegrity($or));
    }

    public function testVerifyIntegrityValidatesFinalSeal(): void
    {
        $or = $this->createOR('signe');

        // Scellé de réception cohérent
        $receptionSnap = $this->policy->buildSnapshot($or);
        $or->setSignedSnapshot($receptionSnap);
        $or->setSignedHash($this->policy->computeHash($receptionSnap));

        // Scellé final cohérent (restitution)
        $finalSnap = ['travaux_realises' => 'Vidange + plaquettes', 'kilometrage_restitution' => 12050];
        $or->setFinalSnapshot($finalSnap);
        $or->setFinalHash($this->policy->computeHash($finalSnap));

        $this->assertTrue($this->policy->verifyIntegrity($or));

        // Falsification du seul scellé final → doit être détectée
        $or->setFinalSnapshot(['travaux_realises' => 'FALSIFIÉ', 'kilometrage_restitution' => 12050]);
        $this->assertFalse($this->policy->verifyIntegrity($or));
    }

    // ─── Régression : le scellé de RÉCEPTION est immuable (finalize supprimé) ───

    public function testReceptionSealSurvivesRestitution(): void
    {
        $or = $this->createOR('brouillon');
        $rdv = $or->getRendezVous();
        $rdv->setDateRdv(new \DateTime('2026-07-20'));
        $rdv->setHeureRdv(new \DateTime('2026-07-20 09:00'));
        $rdv->setTypeIntervention('revision');
        $or->setEtatVehicule(json_encode(['rayures' => false]));

        $request = Request::create('/test', 'POST');

        // 1. Réception signée (client + atelier) → scellé de réception
        $this->policy->signReception($or, 'data:image/png;base64,client', 'data:image/png;base64,atelier', $request);
        $receptionHash = $or->getSignedHash();
        $receptionSnap = $or->getSignedSnapshot();
        $this->assertSame('reception_signee', $or->getStatut());
        $this->assertNotEmpty($receptionHash);

        // 2. Mécanicien signe → intervention_signee
        $this->policy->signMecanicien($or, 'data:image/png;base64,meca', 42);
        $this->assertSame('intervention_signee', $or->getStatut());

        // 3. Client signe la restitution → scellé FINAL posé, réception INTACTE
        $this->policy->signRestitution($or, 'data:image/png;base64,restit');

        $this->assertSame('signe', $or->getStatut());
        $this->assertNotNull($or->getFinalHash(), 'Le scellé final doit être posé à la restitution');
        // INVARIANT CRITIQUE : le scellé de réception n'est JAMAIS réécrit (le bug
        // finalize() le reconstruisait depuis l'état vivant à la transition « terminer »).
        $this->assertSame($receptionHash, $or->getSignedHash(), 'Le scellé de réception doit rester intact après la restitution');
        $this->assertSame($receptionSnap, $or->getSignedSnapshot());
        // Les deux scellés sont distincts et tous deux intègres.
        $this->assertNotSame($or->getSignedHash(), $or->getFinalHash());
        $this->assertTrue($this->policy->verifyIntegrity($or));
    }

    public function testFinalizeMethodIsRemoved(): void
    {
        // finalize() réécrivait signedSnapshot/signedHash depuis l'état vivant et
        // forçait statut='termine' à la transition RDV « terminer » : régression
        // CRITICAL du double scellé. Supprimé — ne pas le réintroduire.
        $this->assertFalse(
            method_exists($this->policy, 'finalize'),
            'finalize() a été supprimé (clobber du scellé de réception) — ne pas le réintroduire.'
        );
    }
}
