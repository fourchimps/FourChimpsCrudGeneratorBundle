
    /**
     * Lists all {{ entity }} entities.
     *
{% if 'annotation' == format %}
     * @Route("/", name="admin_{{ route_name_prefix }}")
     * @Template()
{% endif %}
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository('{{ entity_bundle }}:{{ entity }}')->findAll();

{% if 'annotation' == format %}
        return array(
            'entities' => $entities,
        );
{% else %}
        return $this->render('{{ bundle }}:{{ entity|replace({'\\': '/'}) }}:index.html.twig', array(
            'entities' => $entities,
        ));
{% endif %}
    }
