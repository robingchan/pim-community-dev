<?php
namespace Pim\Bundle\ProductBundle\Controller;

use Pim\Bundle\ProductBundle\Entity\AttributeGroup;
use Pim\Bundle\ProductBundle\Model\AvailableProductAttributes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

/**
 * AttributeGroup controller
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2012 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @Route("/attribute-group")
 */
class AttributeGroupController extends Controller
{

    /**
     * List attribute groups
     *
     * @return multitype
     *
     * @Route("/")
     * @Template()
     */
    public function indexAction()
    {
        return $this->redirect($this->generateUrl('pim_product_attributegroup_create'));
    }

    /**
     * Create attribute group
     *
     * @Route("/create")
     * @Template("PimProductBundle:AttributeGroup:edit.html.twig")
     *
     * @return array
     */
    public function createAction()
    {
        $group = new AttributeGroup();

        return $this->editAction($group);
    }

    /**
     * Edit attribute group
     *
     * @param AttributeGroup $group
     *
     * @Route("/edit/{id}", requirements={"id"="\d+"}, defaults={"id"=0})
     * @Template
     *
     * @return array
     */
    public function editAction(AttributeGroup $group)
    {
        $groups = $this->getAttributeGroupRepository()->getIdToNameOrderedBySortOrder();

        if ($this->get('pim_product.form.handler.attribute_group')->process($group)) {
            $this->addFlash('success', 'Attribute group successfully saved');

            return $this->redirectToAttributeGroupAttributesTab($group->getId());
        }

        return array(
            'groups' => $groups,
            'group'  => $group,
            'form'   => $this->get('pim_product.form.attribute_group')->createView(),
            'attributesForm' => $this->getAvailableProductAttributesForm($this->getGroupedAttributes())->createView()
        );
    }

    /**
     * Edit AttributeGroup sort order
     *
     * @param Request $request
     *
     * @Route("/sort")
     *
     * @return Response
     */
    public function sortAction(Request $request)
    {
        if (!$request->isXmlHttpRequest() || $request->getMethod() !== 'POST') {
            return $this->redirect($this->generateUrl('pim_product_attributegroup_index'));
        }

        $data = $request->request->all();

        $em = $this->getEntityManager();

        if (!empty($data)) {
            foreach ($data as $id => $sort) {
                $group = $this->getAttributeGroupRepository()->find((int) $id);
                if ($group) {
                    $group->setSortOrder((int) $sort);
                    $em->persist($group);
                }
            }
            $em->flush();

            return new Response(1);
        }

        return new Response(0);
    }

    /**
     * Remove attribute group
     *
     * @param AttributeGroup $group
     *
     * @Route("/remove/{id}", requirements={"id"="\d+"})
     * @Method("DELETE")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeAction(AttributeGroup $group)
    {
        $this->getEntityManager()->remove($group);
        $this->getEntityManager()->flush();

        $this->addFlash('success', 'Attribute group successfully removed');

        $request = $this->getRequest();

        if ($request->get('_redirectBack')) {
            $referer = $request->headers->get('referer');
            if ($referer) {
                return $this->redirect($referer);
            }
        }

        return $this->redirect($this->generateUrl('pim_product_attributegroup_index'));
    }

    /**
     * Add attributes to a group
     *
     * @param int $id The group id to add attributes to
     *
     * @return Symfony\Component\HttpFoundation\RedirectResponse
     *
     * @Route("/{id}/attributes", requirements={"id"="\d+", "_method"="POST"})
     *
     */
    public function addProductAttributes($id)
    {
        $group               = $this->findOr404('PimProductBundle:AttributeGroup', $id);
        $availableAttributes = new AvailableProductAttributes;

        $attributesForm      = $this->getAvailableProductAttributesForm(
            $this->getGroupedAttributes(),
            $availableAttributes
        );

        $attributesForm->bind($this->getRequest());

        foreach ($availableAttributes->getAttributes() as $attribute) {
            $group->addAttribute($attribute);
        }

        $this->getEntityManager()->flush();

        return $this->redirectToAttributeGroupAttributesTab($group->getId());
    }

    /**
     * Remove a product attribute
     *
     * @param integer $groupId
     * @param integer $attributeId
     *
     * @Route("/{groupId}/attribute/{attributeId}")
     * @Method("DELETE")
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeProductAttribute($groupId, $attributeId)
    {
        $group     = $this->findOr404('PimProductBundle:AttributeGroup', $groupId);
        $attribute = $this->findOr404('PimProductBundle:ProductAttribute', $attributeId);

        if (false === $group->hasAttribute($attribute)) {
            throw $this->createNotFoundException(
                sprintf('Attribute "%s" is not attached to "%s"', $attribute, $group)
            );
        }

        $group->removeAttribute($attribute);
        $this->getEntityManager()->flush();

        $this->addFlash('success', 'Attribute group successfully updated.');

        return $this->redirectToAttributeGroupAttributesTab($group->getId());
    }

    /**
     * Redirect to attribute tab
     *
     * @param integer $id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectToAttributeGroupAttributesTab($id)
    {
        $url = $this->generateUrl('pim_product_attributegroup_edit', array('id' => $id), false, 'attributes');

        return $this->redirect($url);
    }

    /**
     * Get entity manager
     *
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->getDoctrine()->getEntityManager();
    }

    /**
     * Get attribute group repository
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getAttributeGroupRepository()
    {
        return $this->getEntityManager()->getRepository('PimProductBundle:AttributeGroup');
    }

    /**
     * Get attributes that belong to a group
     *
     * @return array
     */
    protected function getGroupedAttributes()
    {
        return $this->getProductAttributeRepository()->findAllGrouped();
    }
}
