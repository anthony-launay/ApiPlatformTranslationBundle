<?php

declare(strict_types=1);

namespace Locastic\ApiPlatformTranslationBundle\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\PersistentCollection;

/**
 * @see TranslatableInterface
 *
 * @author Gonzalo Vilaseca <gvilaseca@reiss.co.uk>
 */
trait TranslatableTrait
{
    /**
     * @var Collection<TranslationInterface>|TranslationInterface[]
     */
    protected $translations;

    /**
     * @var array|TranslationInterface[]
     */
    protected $translationsCache = [];

    /**
     * @var null|string
     */
    protected $currentLocale;

    /**
     * Cache current translation. Useful in Doctrine 2.4+
     *
     * @var TranslationInterface
     */
    protected $currentTranslation;

    /**
     * @var null|string
     */
    protected $fallbackLocale;

    /**
     * @var null|array
     */
    protected $fallbackLocales;

    /**
     * TranslatableTrait constructor.
     * @codeCoverageIgnore
     */
    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \RuntimeException
     */
    public function getTranslation(?string $locale = null): TranslationInterface
    {
        $locale = $locale ?: $this->currentLocale;
        if (null === $locale) {
            throw new \RuntimeException('No locale has been set and current locale is undefined.');
        }

        if (isset($this->translationsCache[$locale])) {
            return $this->translationsCache[$locale];
        }

        $expr = new Comparison('locale', '=', $locale);
        $translation = $this->translations->matching(new Criteria($expr))->first();

        if (false !== $translation) {
            $this->translationsCache[$locale] = $translation;

            return $translation;
        }

        $translation = $this->getTranslationByFallbackLocale($locale, $this->fallbackLocale);

        if (null !== $translation) {
            return $translation;
        }

        if (is_array($this->fallbackLocales)) {
            foreach ($this->fallbackLocales as $fallbackLocale) {
                $translation = $this->getTranslationByFallbackLocale($locale, $fallbackLocale);

                if (null !== $translation) {
                    return $translation;
                }
            }
        }

        $translation = $this->createTranslation();
        $translation->setLocale($locale);

        $this->addTranslation($translation);

        $this->translationsCache[$locale] = $translation;

        return $translation;
    }

    /**
     * ionInterface|null
     */
    private function getTranslationByFallbackLocale(string $locale, ?string $fallbackLocale) : ?TranslationInterface
    {
        if ($fallbackLocale && ($locale !== $fallbackLocale)) {
            if (isset($this->translationsCache[$fallbackLocale])) {
                return $this->translationsCache[$fallbackLocale];
            }

            $expr = new Comparison('locale', '=', $fallbackLocale);
            $fallbackTranslation = $this->translations->matching(new Criteria($expr))->first();

            if (false !== $fallbackTranslation) {
                $this->translationsCache[$fallbackLocale] = $fallbackTranslation; //@codeCoverageIgnore

                return $fallbackTranslation; //@codeCoverageIgnore
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getTranslationLocales(): array
    {
        $translations = $this->getTranslations();
        $locales = [];

        foreach ($translations as $translation) {
            $locales[] = $translation->getLocale();
        }

        return $locales;
    }

    /**
     * @param string $locale
     */
    public function removeTranslationWithLocale(string $locale): void
    {
        $translations = $this->getTranslations();

        foreach ($translations as $translation) {
            if ($translation->getLocale() === $locale) {
                $this->removeTranslation($translation);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * {@inheritdoc}
     */
    public function hasTranslation(TranslationInterface $translation): bool
    {
        return isset($this->translationsCache[$translation->getLocale()]) || $this->translations->containsKey(
            $translation->getLocale()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addTranslation(TranslationInterface $translation): void
    {
        if (!$this->hasTranslation($translation)) {
            $this->translationsCache[$translation->getLocale()] = $translation;

            $this->translations->set($translation->getLocale(), $translation);
            $translation->setTranslatable($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeTranslation(TranslationInterface $translation): void
    {
        if ($this->translations->removeElement($translation)) {
            unset($this->translationsCache[$translation->getLocale()]);

            $translation->setTranslatable(null);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setCurrentLocale(?string $currentLocale): void
    {
        $this->currentLocale = $currentLocale;
    }

    /**
     * {@inheritdoc}
     */
    public function setFallbackLocale(?string $fallbackLocale): void
    {
        $this->fallbackLocale = $fallbackLocale;
    }

    /**
     * {@inheritdoc}
     */
    public function setFallbackLocales(?array $fallbackLocales): void
    {
        $this->fallbackLocales = $fallbackLocales;
    }

    /**
     * Create resource translation model.
     *
     * @return TranslationInterface
     */
    abstract protected function createTranslation(): TranslationInterface;
}
