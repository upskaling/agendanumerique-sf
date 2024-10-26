<?php

declare(strict_types=1);

namespace App\Controller\Event;

use App\DTO\EventValidationDTO;
use App\Form\ProposeNewType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/event/propose-new', name: 'app_event_propose_new')]
class ProposeNewEventController extends AbstractController
{
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        #[Autowire('%mailer.from_email%')] string $fromEmail,
        #[Autowire('%mailer.admin_email%')] string $adminEmail,
    ): Response {
        $eventValidationDTO = new EventValidationDTO('propose-new');

        $form = $this->createForm(ProposeNewType::class, $eventValidationDTO);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $event = $eventValidationDTO->toEntity();
            $entityManager->persist($event);
            $entityManager->flush();
            $this->addFlash('success', 'Votre événement a bien été proposé');

            $message = (new NotificationEmail())
                ->from($fromEmail)
                ->to($adminEmail)
                ->subject('Un nouvel événement a été proposé')
                ->htmlTemplate('mail/event-propose-new.html.twig')
                ->action('Voir le nouvel événement', $this->generateUrl('app_event_show_slug', ['slug' => $event->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL))
                ->content('Bonjour,
                Un nouvel événement a été proposé sur agendanumerique.fr')
            ;

            $mailer->send($message);

            return $this->redirectToRoute('app_index');
        }

        return $this->render('event/propose-new.html.twig', [
            'form' => $form,
        ]);
    }
}
