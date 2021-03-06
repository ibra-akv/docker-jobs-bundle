<?php

namespace IterativeCode\DockerJobsBundle\Command;

use Doctrine\ORM\EntityNotFoundException;
use IterativeCode\DockerJobsBundle\Entity\BaseJob;
use IterativeCode\DockerJobsBundle\Service\DockerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StopJobCommand extends Command
{
    /** @var ContainerInterface */
    private $container;

    /** @var DockerService */
    private $docker;

    public function __construct(ContainerInterface $container, DockerService $docker, $name = null)
    {
        $this->container = $container;
        $this->docker = $docker;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setName('iterative_code:jobs:stop')
            ->setDescription('Terminates a running job.')
            ->addOption('--job-id', '-j', InputOption::VALUE_REQUIRED, 'Job ID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jobId = $input->getOptions()['job-id'];
        if ($jobId === null) {
            throw new \InvalidArgumentException(sprintf('"--job-id" parameter is required to run this command.'));
        }

        $em = $this->container->get('doctrine')->getManager();
        $jobRepository = $em->getRepository($this->container->getParameter('docker_jobs.class.job'));
        $job = $jobRepository->find($jobId);
        if ($job === null) {
            throw new EntityNotFoundException(sprintf('No such job: %s', $jobId));
        }

        $stopped = $jobId = $this->docker->stopJob($job);
        if ($stopped === true) {
            $job->setState(BaseJob::STATE_STOPPED);
            $em->persist($job);
            $em->flush();

            $output->writeln('<info>Job stopped successfully.</info>');

            return 0;
        } else {
            $output->writeln('<comment>Something went wrong, could not stop job.</comment>');

            return 1;
        }
    }
}
