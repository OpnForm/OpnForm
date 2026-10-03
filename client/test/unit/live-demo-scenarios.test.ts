import { describe, expect, it } from "vitest"
import { getLiveDemoScenario } from "../../data/live-demo-scenarios.js"

const demoVariants = [
  { variant: "home" },
  { variant: "comparison", competitorName: "Typeform" },
  { variant: "comparison", competitorName: "Google Forms" },
  { variant: "comparison", competitorName: "Tally" },
  { variant: "comparison", competitorName: "Jotform" },
  { variant: "comparison", competitorName: "Fillout" },
  { variant: "comparison", competitorName: "HeyForm" },
  { variant: "comparison", competitorName: "Youform" },
  { variant: "comparison", competitorName: "Formbricks" },
  { variant: "comparison", competitorName: "Form.io" },
  { variant: "comparison", competitorName: "123FormBuilder" },
  { variant: "comparison", competitorName: "Another form builder" },
]

describe("live demo scenarios", () => {
  it.each(demoVariants)(
    "keeps the $variant demo for $competitorName in English",
    (variant) => {
      const scenario = getLiveDemoScenario(variant)

      expect(scenario.form.language).toBe("en")
      expect(scenario.form.translations.focused_next_button_text).toBe("Next")
    },
  )
})
