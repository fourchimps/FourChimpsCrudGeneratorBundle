<?php

## app/console fourchimps:doctrine:generate:crud --entity="FourChimpsArticleBundle:Article" --target="FourChimpsAdminBundle" --route-prefix="/article" --with-write --format="annotation"



namespace FourChimps\CrudGeneratorBundle\Command;

use FourChimps\CrudGeneratorBundle\Generator\DoctrineCrudGenerator;
use FourChimps\CrudGeneratorBundle\Generator\DoctrineFormGenerator;
use \Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand as GenerateDoctrineCrudCommand ;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Sensio\Bundle\GeneratorBundle\Command\Validators;

use Doctrine\Common\Annotations\AnnotationRegistry;

class CrudGeneratorBundleCrudCommand extends GenerateDoctrineCrudCommand {


    protected function configure() {
        $this
            ->setDefinition(array(
            new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
            new InputOption('target', '', InputOption::VALUE_REQUIRED, 'The Bundle your adding the CRUD actions to'),
            new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
            new InputOption('with-write', '', InputOption::VALUE_NONE, 'Whether or not to generate create, new and delete actions'),
            new InputOption('format', '', InputOption::VALUE_REQUIRED, 'Use the format for configuration files (php, xml, yml, or annotation)', 'annotation'),
        ))
            ->setDescription('Generates a CRUD based on a Doctrine entity')
            ->setHelp(<<<EOT
The <info>fourchimps:doctrine:generate:crud</info> command generates a CRUD based on a Doctrine entity - in a host.target bundle (i.e. Admin)

The default command only generates the list and show actions.

<info>php app/console doctrine:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=post_admin</info>

Using the --with-write option allows to generate the new, edit and delete actions.

<info>php app/console doctrine:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=post_admin --with-write</info>
EOT
        )
            ->setName('fourchimps:doctrine:generate:crud');
	}

	protected function getGenerator() {
		$generator = new DoctrineCrudGenerator(
				$this->getContainer()->get('filesystem'),
				__DIR__ . '/../Resources/skeleton/crud');

		$this->setGenerator($generator);
		return parent::getGenerator();
	}

    protected function getFormGenerator()
    {
        $formGenerator = new DoctrineFormGenerator(
            $this->getContainer()->get('filesystem'),
            __DIR__.'/../Resources/skeleton/form');

        $this->setFormGenerator($formGenerator);
        return parent::getFormGenerator();
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Welcome to the Doctrine2 CRUD generator');

        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate CRUD controllers and templates.',
            '',
            'First, you need to give the entity for which you want to generate a CRUD.',
            'You can give an entity that does not exist yet and the wizard will help',
            'you defining it.',
            '',
            'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
            '',
        ));

        $entity = $dialog->askAndValidate($output, $dialog->getQuestion('The Entity shortcut name', $input->getOption('entity')), array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'), false, $input->getOption('entity'));
        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        // Entity exists?
        $entityClass = $this->getContainer()->get('doctrine')->getEntityNamespace($bundle).'\\'.$entity;
        $metadata = $this->getEntityMetadata($entityClass);

        // target
        $output->writeln(array(
            '',
            'The FourChimps CRUD generator creates CRUD actions in a host or target bundle.',
            '',
        ));
        $target = $dialog->askAndValidate($output, $dialog->getQuestion('The Target bundle', $input->getOption('target')),
            array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName'), false, $input->getOption('target'));
        $input->setOption('target', $target);

        // write?
        $withWrite = $input->getOption('with-write') ?: false;
        $output->writeln(array(
            '',
            'By default, the generator creates two actions: list and show.',
            'You can also ask it to generate "write" actions: new, update, and delete.',
            '',
        ));
        $withWrite = $dialog->askConfirmation($output, $dialog->getQuestion('Do you want to generate the "write" actions', $withWrite ? 'yes' : 'no', '?'), $withWrite);
        $input->setOption('with-write', $withWrite);

        // format
        $format = $input->getOption('format');
        $output->writeln(array(
            '',
            'Determine the format to use for the generated CRUD.',
            '',
        ));
        $format = $dialog->askAndValidate($output, $dialog->getQuestion('Configuration format (yml, xml, php, or annotation)', $format), array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateFormat'), false, $format);
        $input->setOption('format', $format);

        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(array(
            '',
            'Determine the routes prefix (all the routes will be "mounted" under this',
            'prefix: /prefix/, /prefix/new, ...).',
            '',
        ));
        $prefix = $dialog->ask($output, $dialog->getQuestion('Routes prefix', '/'.$prefix), '/'.$prefix);
        $input->setOption('route-prefix', $prefix);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf("You are going to generate a CRUD controller for \"<info>%s:%s</info>\"", $bundle, $entity),
            sprintf("using the \"<info>%s</info>\" format.", $format),
            sprintf("in the \"<info>%s</info>\" Bundle.", $target),
            '',
        ));
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();

        if ($input->isInteractive()) {
            if (!$dialog->askConfirmation($output, $dialog->getQuestion('Do you confirm generation', 'yes', '?'), true)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($entityBundleName, $entity) = $this->parseShortcutNotation($entity);

        try {
            $entityBundle = $this->getContainer()->get('kernel')->getBundle($entityBundleName);
        } catch (\Exception $e) {
            $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $entityBundleName));
        }

        $bundle = Validators::validateBundleName($input->getOption('target'));

        $format = Validators::validateFormat($input->getOption('format'));
        $prefix = $this->getRoutePrefix($input, $entity);
        $withWrite = $input->getOption('with-write');

        $dialog->writeSection($output, 'CRUD generation');

        $entityClass = $this->getContainer()->get('doctrine')->getEntityNamespace($entityBundleName).'\\'.$entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);

        $generator = $this->getGenerator();
        $generator->setEntityBundle($entityBundle);
        $generator->setEntityBundleName($entityBundleName);

        $generator->generate($bundle, $entity, $metadata[0], $format, $prefix, $withWrite);

        $output->writeln('Generating the CRUD code: <info>OK</info>');

        $errors = array();
        $runner = $dialog->getRunner($output, $errors);

        // form
        if ($withWrite) {
            try {
                $this->getFormGenerator()
                    ->setEntityBundle($entityBundle)
                    ->generate($bundle, $entity, $metadata[0]);
            } catch (\RuntimeException $e ) {
                // form already exists
            }

            $output->writeln('Generating the Form code: <info>OK</info>');
        }

        // routing
        if ('annotation' != $format) {
            $runner($this->updateRouting($dialog, $input, $output, $bundle, $format, $entity, $prefix));
        }

        $dialog->writeGeneratorSummary($output, $errors);
    }

    /**
     * Tries to generate forms if they don't exist yet and if we need write operations on entities.
     */
    private function generateForm($bundle, $entity, $metadata)
    {


    }
}
