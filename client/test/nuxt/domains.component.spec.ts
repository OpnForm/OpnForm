import { describe, it, expect } from 'vitest'

/**
 * Unit tests for domain validation logic in Domains.vue
 * Tests the domain regex validation that validates custom domains
 * Matches backend regex: /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,20}$/
 */
describe('Domains Component - Domain Validation Regex', () => {
  // Domain validation regex from Domains.vue
  const domainRegex = /^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,20}$/i

  describe('Valid domains - should PASS validation', () => {
    it('accepts simple domains like example.com', () => {
      const domain = 'example.com'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('accepts multi-part TLD domains like .co.uk', () => {
      const domain = 'example.co.uk'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('accepts subdomains with multi-part TLDs', () => {
      const domain = 'test.domain.co.uk'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('accepts various multi-part TLDs', () => {
      const domains = [
        'test.co.nz',
        'domain.com.au',
        'example.co.jp',
        'subdomain.example.co.uk',
        'api.mysite.co.uk',
        'mail.example.co.nz',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(true)
      })
    })

    it('accepts domains with hyphens', () => {
      const domains = [
        'my-domain.com',
        'test-site.co.uk',
        'api-gateway.example.com',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(true)
      })
    })

    it('accepts domains with numbers', () => {
      const domains = [
        'example123.com',
        'test123.co.uk',
        '123.com',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(true)
      })
    })

    it('accepts uppercase domains (case-insensitive)', () => {
      const domains = [
        'EXAMPLE.COM',
        'Example.Com',
        'EXAMPLE.CO.UK',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(true)
      })
    })
  })

  describe('Invalid domains - should FAIL validation', () => {
    it('rejects domains without TLD', () => {
      const domain = 'localhost'
      expect(domainRegex.test(domain)).toBe(false)
    })

    it('rejects domains with no dot', () => {
      const domain = 'invalid-domain'
      expect(domainRegex.test(domain)).toBe(false)
    })

    it('rejects domains starting with dash', () => {
      const domains = [
        '-invalid.com',
        '-test.co.uk',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(false)
      })
    })

    it('rejects domains starting with dot', () => {
      const domain = '.example.com'
      expect(domainRegex.test(domain)).toBe(false)
    })

    it('rejects domains with consecutive dots', () => {
      const domains = [
        'test..com',
        'example..co.uk',
        'api...example.com',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(false)
      })
    })

    it('rejects domains ending with dot', () => {
      const domain = 'example.com.'
      expect(domainRegex.test(domain)).toBe(false)
    })

    it('rejects domains with invalid characters', () => {
      const domains = [
        'test@example.com',
        'test@test/',
        'ex ample.com',
        'test_domain.com',
        'test#example.com',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(false)
      })
    })

    it('rejects TLD that is too long (>20 chars)', () => {
      const domain = 'example.verylongextensionmorethan20chars'
      expect(domainRegex.test(domain)).toBe(false)
    })

    it('rejects domains with ending hyphen', () => {
      const domains = [
        'example-.com',
        'test-.co.uk',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(false)
      })
    })

    it('rejects URLs with protocol', () => {
      const domains = [
        'http://example.com',
        'https://example.com',
        'ftp://example.com',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(false)
      })
    })

    it('rejects URLs with paths', () => {
      const domains = [
        'example.com/',
        'example.com/path',
        'example.com/path/to/page',
      ]

      domains.forEach(domain => {
        expect(domainRegex.test(domain)).toBe(false)
      })
    })
  })

  describe('Input cleaning - domain extraction logic', () => {
    it('extracts clean domain from URL with protocol', () => {
      const url = 'https://example.com'
      const cleanedDomain = url
        .replace(/^https?:\/\//i, '')
        .split('/')[0]

      expect(cleanedDomain).toBe('example.com')
      expect(domainRegex.test(cleanedDomain)).toBe(true)
    })

    it('extracts clean domain from URL with path', () => {
      const url = 'example.com/path/to/page'
      const cleanedDomain = url.split('/')[0]

      expect(cleanedDomain).toBe('example.com')
      expect(domainRegex.test(cleanedDomain)).toBe(true)
    })

    it('extracts clean domain from full URL', () => {
      const url = 'https://test.co.uk/some/path'
      const cleanedDomain = url
        .replace(/^https?:\/\//i, '')
        .split('/')[0]

      expect(cleanedDomain).toBe('test.co.uk')
      expect(domainRegex.test(cleanedDomain)).toBe(true)
    })

    it('preserves clean domain when already clean', () => {
      const domain = 'example.co.uk'
      const cleanedDomain = domain
        .replace(/^https?:\/\//i, '')
        .split('/')[0]

      expect(cleanedDomain).toBe('example.co.uk')
      expect(domainRegex.test(cleanedDomain)).toBe(true)
    })
  })

  describe('Edge cases', () => {
    it('accepts minimal valid domain (2 char domain + 2 char TLD)', () => {
      const domain = 'ab.cd'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('accepts domain with many subdomains', () => {
      const domain = 'api.v1.staging.example.co.uk'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('accepts domain with numbers at start', () => {
      const domain = '123.example.com'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('accepts very long valid domain', () => {
      const domain = 'very-long-subdomain-name-that-is-still-valid.example.co.uk'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('rejects single segment TLD', () => {
      const domain = 'example.c'
      expect(domainRegex.test(domain)).toBe(false)
    })

    it('accepts exactly 20 char TLD', () => {
      // TLD can be max 20 chars
      const domain = 'example.abcdefghijklmnopqrst'
      expect(domainRegex.test(domain)).toBe(true)
    })

    it('rejects 21 char TLD', () => {
      const domain = 'example.abcdefghijklmnopqrstu'
      expect(domainRegex.test(domain)).toBe(false)
    })
  })

  describe('Regex consistency with backend', () => {
    // Backend regex from CustomDomainRequest.php: /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,20}$/
    // Frontend regex: /^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,20}$/i
    // Both should match the same valid domains

    it('both regex patterns match valid domains', () => {
      const backendRegex = /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,20}$/
      const frontendRegex = /^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,20}$/i

      const testDomains = [
        'example.com',
        'test.co.uk',
        'subdomain.example.co.uk',
        'api-v1.example.com',
        '123.example.co.nz',
      ]

      testDomains.forEach(domain => {
        const backendMatch = backendRegex.test(domain.toLowerCase())
        const frontendMatch = frontendRegex.test(domain.toLowerCase())

        expect(backendMatch).toBe(true)
        expect(frontendMatch).toBe(true)
        expect(backendMatch).toBe(frontendMatch)
      })
    })

    it('both regex patterns reject invalid domains', () => {
      const backendRegex = /^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,20}$/
      const frontendRegex = /^[a-z0-9]+([-.][a-z0-9]+)*\.[a-z]{2,20}$/i

      const testDomains = [
        'invalid-domain',
        '-invalid.com',
        'test..com',
        'test@example.com',
      ]

      testDomains.forEach(domain => {
        const backendMatch = backendRegex.test(domain.toLowerCase())
        const frontendMatch = frontendRegex.test(domain.toLowerCase())

        expect(backendMatch).toBe(false)
        expect(frontendMatch).toBe(false)
        expect(backendMatch).toBe(frontendMatch)
      })
    })
  })
})
