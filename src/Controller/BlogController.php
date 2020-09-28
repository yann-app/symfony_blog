<?php

namespace App\Controller;

use App\Form\SearchType;
use App\Repository\ArticleRepository;
use App\Entity\Article;
use App\Entity\User;
use App\Form\ArticleType;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BlogController extends AbstractController
{


    public function index()
    {
        $articles = $this->getDoctrine()->getRepository(Article::class)->findBy(
            ['isPublished' => true],
            ['publicationDate' => 'desc']
        );
        return $this->render('index.html.twig', [
            'article' => $articles
        ]);
    }


    public function edit(Article $article, Request $request)
    {
        $oldPicture = $article->getPicture();

        $form = $this->createForm(ArticleType::class, $article);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $article->setLastUpdateDate(new \DateTime());

            if ($article->getPicture() !== null && $article->getPicture() !== $oldPicture) {
                $file = $form->get('picture')->getData();
                $filename = uniqid() . '.' . $file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('images_directory'),
                        $filename
                    );
                } catch (FileException $e) {
                    return new Response($e->getMessage());
                }
                $article->setPicture($filename);
            } else {
                $article->setPicture($oldPicture);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('admin');
        }

        return $this->render('edit.html.twig', [
            'article' => $article,
            'form' => $form->createView()
        ]);

    }


    public function add(Request $request)
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $article->setLastUpdateDate(new \DateTime());

            if ($article->getPicture() !== null) {
                $file = $form->get('picture')->getData();
                $filename = uniqid() . '.' . $file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('images_directory'),
                        $filename
                    );
                } catch (FileException $e) {
                    return new Response($e->getMessage());
                }
                $article->setPicture($filename);
            }

            if ($article->getIsPublished()) {
                $article->setPublicationDate(new \DateTime());
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('homepage');
        }

        return $this->render('add.html.twig', [
            'form' => $form->createView()
        ]);
    }


    public function show(Article $article)
    {
        return $this->render('show.html.twig', [
            'article' => $article
        ]);
    }

    public function admin()
    {
        $articles = $this->getDoctrine()->getRepository(Article::class)->findBy(
            [],
            ['lastUpdateDate' => 'DESC']
        );

        $users = $this->getDoctrine()->getRepository(User::class)->findAll();

        return $this->render('admin/index.html.twig', [
            'articles' => $articles,
            'users' => $users
        ]);
    }

    public function remove($id)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $item = $entityManager->getRepository(Article::class)->find($id);

        $entityManager->remove($item);
        $entityManager->flush();

        return $this->redirectToRoute('admin');

    }

    public function admin_user_delete($id)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $item = $entityManager->getRepository(User::class)->find($id);

        $entityManager->remove($item);
        $entityManager->flush();

        return $this->redirectToRoute('admin');
    }



    public function recherche(Request $request, ArticleRepository $repo, PaginatorInterface $paginator)
    {

        $searchForm = $this->createForm(SearchType::class);
        $searchForm->handleRequest($request);

        $donnees = $repo->findAll();

        if ($searchForm->isSubmitted() && $searchForm->isValid()) {

            $title = $searchForm->getData()->getTitle();

            $donnees = $repo->search($title);


            if ($donnees == null) {
                $this->addFlash('erreur',
                    'Aucun article contenant ce mot clé dans le titre n\'a été trouvé, essayez en un autre.');

            }

        }

        // Paginate the results of the query
        $articles = $paginator->paginate(
        // Doctrine Query, not results
            $donnees,
            // Define the page parameter
            $request->query->getInt('page', 1),
            // Items per page
            4

        );

                return $this->render('search.html.twig', [
                'articles' => $articles,
                'searchForm' => $searchForm->createView()
            ]);
        }
}